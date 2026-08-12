<?php

namespace Tests\Feature\Audit;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AuditCampaign;
use App\Models\AuditItem;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CampaignActivationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_activation_snapshots_only_assets_matching_scope(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);

        $matching = Asset::factory()->count(2)->create(['location_id' => $location->id]);
        $notMatching = Asset::factory()->create();

        $campaign = AuditCampaign::factory()->create(['scope' => ['location_id' => $location->id]]);
        $campaign->auditors()->sync([$auditor->id]);

        $count = app(AuditService::class)->activate($campaign, $manager);

        $this->assertSame(2, $count);
        foreach ($matching as $asset) {
            $this->assertDatabaseHas('audit_items', ['campaign_id' => $campaign->id, 'asset_id' => $asset->id]);
        }
        $this->assertDatabaseMissing('audit_items', ['campaign_id' => $campaign->id, 'asset_id' => $notMatching->id]);
        $this->assertSame('active', $campaign->fresh()->status);
    }

    public function test_activation_excludes_disposed_assets(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $location = Location::create(['name' => 'HQ', 'code' => 'HQ', 'is_active' => true]);
        $disposedStatus = AssetStatus::create(['name' => 'Disposed', 'code' => 'DISPOSED', 'color' => '#ef4444', 'is_system' => true, 'is_active' => true]);

        $active = Asset::factory()->create(['location_id' => $location->id]);
        $disposed = Asset::factory()->create(['location_id' => $location->id, 'status_id' => $disposedStatus->id]);

        $campaign = AuditCampaign::factory()->create(['scope' => ['location_id' => $location->id]]);
        $campaign->auditors()->sync([$auditor->id]);

        app(AuditService::class)->activate($campaign, $manager);

        $this->assertDatabaseHas('audit_items', ['campaign_id' => $campaign->id, 'asset_id' => $active->id]);
        $this->assertDatabaseMissing('audit_items', ['campaign_id' => $campaign->id, 'asset_id' => $disposed->id]);
    }

    public function test_activation_snapshots_expected_location_and_custodian(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $custodian = Employee::factory()->create();
        $location = Location::create(['name' => 'Branch', 'code' => 'BR', 'is_active' => true]);
        $asset = Asset::factory()->create(['location_id' => $location->id, 'custodian_id' => $custodian->id]);

        $campaign = AuditCampaign::factory()->create(['scope' => ['location_id' => $location->id]]);
        $campaign->auditors()->sync([$auditor->id]);

        app(AuditService::class)->activate($campaign, $manager);

        $item = AuditItem::where('campaign_id', $campaign->id)->firstOrFail();
        $this->assertSame($location->id, $item->expected_location_id);
        $this->assertSame($custodian->id, $item->expected_custodian_id);
    }

    public function test_cannot_activate_twice(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);
        $campaign->auditors()->sync([$auditor->id]);

        app(AuditService::class)->activate($campaign, $manager);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AuditService::class)->activate($campaign->fresh(), $manager);
    }

    public function test_cannot_activate_without_auditors(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AuditService::class)->activate($campaign, $manager);
    }

    public function test_cannot_activate_without_scope(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $campaign = AuditCampaign::factory()->create(['scope' => []]);
        $campaign->auditors()->sync([$auditor->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(AuditService::class)->activate($campaign, $manager);
    }

    public function test_assigned_auditors_are_notified_on_activation(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $auditor = $this->createUserWithRole('Auditor');
        $otherAuditor = $this->createUserWithRole('Auditor');
        $asset = Asset::factory()->create();
        $campaign = AuditCampaign::factory()->create(['scope' => ['company_id' => $asset->company_id]]);
        $campaign->auditors()->sync([$auditor->id]);

        app(AuditService::class)->activate($campaign, $manager);

        Notification::assertSentTo($auditor, GenericNotification::class, fn ($n) => $n->payload['campaign_id'] === $campaign->id);
        Notification::assertNotSentTo($otherAuditor, GenericNotification::class);
    }
}
