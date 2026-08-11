<?php

namespace Tests\Feature\Maintenance;

use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class SendExpiryAlertsCommandTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_command_runs_and_reports_alerts_sent(): void
    {
        Notification::fake();
        $this->createUserWithRole('Asset Manager');
        Asset::factory()->create(['warranty_expiry' => now()->addDays(7)]);

        $this->artisan('alerts:expiry')
            ->expectsOutputToContain('Sent 1 expiry alert')
            ->assertExitCode(0);
    }
}
