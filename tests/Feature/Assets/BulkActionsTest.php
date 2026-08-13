<?php

namespace Tests\Feature\Assets;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_export_selected_streams_a_file(): void
    {
        $assets = Asset::factory()->count(2)->create();

        $this->actingAs($this->admin())
             ->post(route('assets.export.store'), ['asset_ids' => $assets->pluck('id')->all()])
             ->assertOk();
    }

    public function test_print_list_returns_a_pdf(): void
    {
        $assets = Asset::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
             ->post(route('assets.print-list'), ['asset_ids' => $assets->pluck('id')->all()]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_bulk_submit_from_draft_submits_only_draft_assets(): void
    {
        $wf = ApprovalWorkflow::factory()->module('asset_creation')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);

        $draft = AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true]);
        $available = AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);

        $d1 = Asset::factory()->create(['status_id' => $draft->id]);
        $d2 = Asset::factory()->create(['status_id' => $draft->id]);
        $live = Asset::factory()->create(['status_id' => $available->id]);

        $this->actingAs($this->admin())
             ->post(route('assets.bulk-submit-approval'), ['asset_ids' => [$d1->id, $d2->id, $live->id]])
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertTrue($d1->fresh()->hasPendingCreationApproval());
        $this->assertTrue($d2->fresh()->hasPendingCreationApproval());
        $this->assertFalse($live->fresh()->hasPendingCreationApproval());
        $this->assertDatabaseCount('approval_requests', 2);
    }

    public function test_bulk_submit_reports_when_no_workflow_configured(): void
    {
        $draft = AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true]);
        $asset = Asset::factory()->create(['status_id' => $draft->id]);

        $this->actingAs($this->admin())
             ->post(route('assets.bulk-submit-approval'), ['asset_ids' => [$asset->id]])
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertDatabaseCount('approval_requests', 0);
    }
}
