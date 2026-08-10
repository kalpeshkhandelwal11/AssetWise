<?php

namespace Tests\Feature\Movement;

use App\Models\Asset;
use App\Models\AssetMovementBatch;
use App\Models\MovementType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Regressions found by driving the real UI in a browser rather than posting straight to
 * endpoints — the existing feature tests never rendered these pages, so both defects
 * shipped green.
 */
class MovementViewRegressionTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    /**
     * Blade's @json splits its expression on commas, so @json($x->pluck('code', 'id'))
     * silently used " 'id')" as the flags argument, dropping JSON_HEX_QUOT. The raw double
     * quotes then terminated the surrounding x-data="..." attribute and Alpine failed to
     * evaluate the component at all, so no conditional field on the wizard ever appeared.
     */
    public function test_movement_wizard_x_data_attribute_is_not_broken_by_raw_quotes(): void
    {
        MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $user = $this->createUserWithRole('Asset Manager');

        $html = $this->actingAs($user)->get(route('movements.create'))->assertOk()->getContent();

        // The type map must be embedded without bare double quotes, or the attribute ends early.
        $this->assertStringNotContainsString('typeCodes: {"', $html);
        $this->assertMatchesRegularExpression('/typeCodes:\s*JSON\.parse\(/', $html);
        $this->assertStringContainsString('ASSIGNMENT', $html);
    }

    public function test_bulk_wizard_x_data_attribute_is_not_broken_by_raw_quotes(): void
    {
        MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $asset = Asset::factory()->create();
        $user = $this->createUserWithRole('Asset Manager');

        $html = $this->actingAs($user)
            ->get(route('movements.bulk.create', ['asset_ids' => [$asset->id]]))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('typeCodes: {"', $html);
        $this->assertMatchesRegularExpression('/typeCodes:\s*JSON\.parse\(/', $html);
    }

    /**
     * The index used to filter out every movement carrying a batch_id, on the assumption
     * that the batch would be listed under its own row — nothing ever rendered that row,
     * so a submitted bulk movement was invisible in Movement History.
     */
    public function test_movement_history_lists_bulk_batch_members(): void
    {
        $type = MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $user = $this->createUserWithRole('Asset Manager');
        $assets = Asset::factory()->count(2)->create();

        $batch = AssetMovementBatch::create([
            'movement_type_id' => $type->id,
            'to_custodian_id'  => $user->id,
            'status'           => 'pending_approval',
            'requested_by'     => $user->id,
        ]);

        foreach ($assets as $asset) {
            \App\Models\AssetMovement::create([
                'asset_id'         => $asset->id,
                'movement_type_id' => $type->id,
                'batch_id'         => $batch->id,
                'to_custodian_id'  => $user->id,
                'status'           => 'pending_approval',
                'requested_by'     => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->get(route('movements.index'))->assertOk();

        foreach ($assets as $asset) {
            $response->assertSee($asset->name);
        }
        $response->assertSee('Bulk'); // grouping is still signalled on each row
    }

    /**
     * WorkflowService reports configuration problems under the 'workflow' key, which
     * matches no field on these forms — without an explicit block the submission failed
     * with no visible message at all.
     */
    public function test_missing_workflow_error_is_rendered_on_the_movement_wizard(): void
    {
        $type = MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $asset = Asset::factory()->create();
        $custodian = User::factory()->create();
        $user = $this->createUserWithRole('Asset Manager');

        // No transfer workflow seeded in this test, so submit() throws under 'workflow'.
        // Follow the redirect so the re-rendered form is what gets asserted against —
        // that is the page the user actually lands on, and where the message was missing.
        // from() sets the referer so Laravel's redirect-back lands on the wizard rather
        // than falling through to "/" (which is where an unset referer sends it in tests).
        $this->actingAs($user)
            ->from(route('movements.create'))
            ->followingRedirects()
            ->post(route('movements.store'), [
                'asset_id'         => $asset->id,
                'movement_type_id' => $type->id,
                'to_custodian_id'  => $custodian->id,
            ])
            ->assertOk()
            ->assertSee('No active approval workflow is configured');

        $this->assertDatabaseCount('asset_movements', 0); // submit() is transactional
    }
}
