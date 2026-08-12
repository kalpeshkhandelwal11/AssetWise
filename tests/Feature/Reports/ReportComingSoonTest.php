<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ReportComingSoonTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public static function disabledTypes(): array
    {
        // 'maintenance' promoted to enabled and 'amc_warranty' added in the M14 pending
        // reports follow-up — only 'kit_assignment_history' remains blocked, on M17.
        return [
            ['kit_assignment_history'],
        ];
    }

    #[DataProvider('disabledTypes')]
    public function test_disabled_report_type_404s_even_for_super_admin(string $type): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->get(route('reports.show', $type))->assertNotFound();
    }

    #[DataProvider('disabledTypes')]
    public function test_disabled_report_export_404s_even_for_super_admin(string $type): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->post(route('reports.export', $type))->assertNotFound();
    }

    public function test_unknown_report_type_404s(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->get(route('reports.show', 'not_a_real_report'))->assertNotFound();
    }

    public function test_index_renders_disabled_types_without_a_working_link(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk();
        $response->assertDontSee(route('reports.show', 'kit_assignment_history'), false);
        $response->assertSee('Coming soon');
    }
}
