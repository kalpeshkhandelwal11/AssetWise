<?php

namespace Tests\Feature\Maintenance;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\ExpiryAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ExpiryAlertServiceTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_fires_at_exactly_30_7_and_1_day_thresholds(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');

        $due30 = Asset::factory()->create(['warranty_expiry' => now()->addDays(30)]);
        $due7 = Asset::factory()->create(['warranty_expiry' => now()->addDays(7)]);
        $due1 = Asset::factory()->create(['warranty_expiry' => now()->addDays(1)]);
        $notDue = Asset::factory()->create(['warranty_expiry' => now()->addDays(15)]);

        $sent = app(ExpiryAlertService::class)->run();

        $this->assertSame(3, $sent);
        Notification::assertSentTo($manager, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $due30->id);
        Notification::assertSentTo($manager, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $due7->id);
        Notification::assertSentTo($manager, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $due1->id);
        Notification::assertNotSentTo($manager, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $notDue->id);
    }

    public function test_does_not_fire_for_amc_or_warranty_expiries_outside_thresholds(): void
    {
        Notification::fake();
        $this->createUserWithRole('Asset Manager');

        Asset::factory()->create(['warranty_expiry' => now()->addDays(45)]);
        Asset::factory()->create(['amc_expiry' => now()->subDays(2)]);

        $sent = app(ExpiryAlertService::class)->run();

        $this->assertSame(0, $sent);
    }

    public function test_notifies_maintenance_manage_users_and_the_assets_custodian(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        // Custodian is an employee linked to a login account — alerts reach the linked user.
        $custodianUser = User::factory()->create();
        $custodian = Employee::factory()->create(['user_id' => $custodianUser->id]);

        $asset = Asset::factory()->create(['amc_expiry' => now()->addDays(7), 'custodian_id' => $custodian->id]);

        app(ExpiryAlertService::class)->run();

        Notification::assertSentTo($manager, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $asset->id);
        Notification::assertSentTo($custodianUser, GenericNotification::class, fn ($n) => $n->payload['asset_id'] === $asset->id);
    }

    public function test_does_not_double_notify_when_custodian_already_holds_maintenance_manage(): void
    {
        Notification::fake();
        $manager = $this->createUserWithRole('Asset Manager');
        $employee = Employee::factory()->create(['user_id' => $manager->id]);

        $asset = Asset::factory()->create(['amc_expiry' => now()->addDays(7), 'custodian_id' => $employee->id]);

        app(ExpiryAlertService::class)->run();

        Notification::assertSentToTimes($manager, GenericNotification::class, 1);
    }
}
