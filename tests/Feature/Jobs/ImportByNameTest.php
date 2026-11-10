<?php

namespace Tests\Feature\Jobs;

use App\Exports\AssetTemplateExport;
use App\Jobs\ProcessAssetImport;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use App\Services\NotificationService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Bulk import references masters by name/code (never ids), enforces unique serials and
 * unique custom fields (in the DB and inside the file), skips the template sample row, and —
 * with an active asset_creation workflow — parks every row in DRAFT under ONE batch approval.
 */
class ImportByNameTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private Company $company;

    private AssetCategory $category;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['code' => 'ACME', 'name' => 'Acme Ltd', 'is_active' => true]);
        $this->category = AssetCategory::factory()->create();
        AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        AssetStatus::create(['name' => 'Draft', 'code' => 'DRAFT', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true]);
        Location::create(['name' => 'Head Office', 'code' => 'HO', 'is_active' => true]);
        $this->user = User::factory()->create();
    }

    private function import(array $headings, array $rows): ImportBatch
    {
        Storage::fake('local');

        $export = new class($headings, $rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $headings, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $path = 'imports/test-'.uniqid().'.xlsx';
        Excel::store($export, $path, 'local');

        $batch = ImportBatch::create([
            'category_id' => $this->category->id,
            'user_id'     => $this->user->id,
            'filename'    => 'assets.xlsx',
            'status'      => 'processing',
        ]);

        (new ProcessAssetImport($batch->id, $path))->handle(
            app(AssetService::class), app(DynamicFieldService::class), app(NotificationService::class),
        );

        return $batch->fresh();
    }

    private function errorsFor(ImportBatch $batch, int $rowNumber): array
    {
        return ImportBatchRow::where('batch_id', $batch->id)->where('row_number', $rowNumber)->value('errors') ?? [];
    }

    public function test_masters_resolve_by_name_or_code_case_insensitively(): void
    {
        $employee = Employee::factory()->create(['name' => 'Ravi Kumar', 'employee_code' => 'EMP001']);

        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status', 'location', 'custodian'],
            [['Laptop', 'acme', 'hardware', 'AVAILABLE', 'head office', 'Ravi Kumar (EMP001)']],
        );

        $this->assertSame(1, $batch->success_count, json_encode($this->errorsFor($batch, 2)));
        $asset = Asset::firstOrFail();
        $this->assertSame($this->company->id, $asset->company_id);
        $this->assertSame(Location::where('code', 'HO')->value('id'), $asset->location_id);
        $this->assertSame($employee->id, $asset->custodian_id);
    }

    public function test_unknown_and_ambiguous_names_fail_the_row_with_a_clear_message(): void
    {
        Employee::factory()->create(['name' => 'Same Name', 'employee_code' => 'E1']);
        Employee::factory()->create(['name' => 'Same Name', 'employee_code' => 'E2']);

        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status', 'location', 'custodian'],
            [
                ['A', 'ACME', 'Hardware', 'Available', 'Nowhere', ''],
                ['B', 'ACME', 'Hardware', 'Available', '', 'Same Name'],
            ],
        );

        $this->assertSame(0, $batch->success_count);
        $this->assertStringContainsString('Unknown location', json_encode($this->errorsFor($batch, 2)));
        $this->assertStringContainsString('Ambiguous custodian', json_encode($this->errorsFor($batch, 3)));
    }

    public function test_legacy_id_columns_still_import(): void
    {
        $batch = $this->import(
            ['name', 'company_code', 'asset_type_id', 'status_id'],
            [['Legacy', 'ACME', AssetType::value('id'), AssetStatus::where('code', 'AVAILABLE')->value('id')]],
        );

        $this->assertSame(1, $batch->success_count);
    }

    public function test_sample_row_is_skipped_and_workflow_statuses_are_rejected(): void
    {
        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status'],
            [
                ['SAMPLE — delete this row: Old', 'ACME', 'Hardware', 'Available'],
                ['Sneaky Draft', 'ACME', 'Hardware', 'Draft'],
            ],
        );

        $this->assertSame(1, $batch->total_rows); // sample row produced no record at all
        $this->assertStringContainsString('cannot be set by import', json_encode($this->errorsFor($batch, 3)));
    }

    public function test_duplicate_serials_are_caught_in_the_database_and_within_the_file(): void
    {
        Asset::factory()->create(['serial_number' => 'SN-EXISTS']);

        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status', 'serial_number'],
            [
                ['One', 'ACME', 'Hardware', 'Available', 'sn-exists'],
                ['Two', 'ACME', 'Hardware', 'Available', 'SN-NEW'],
                ['Three', 'ACME', 'Hardware', 'Available', 'SN-NEW'],
                ['Four', 'ACME', 'Hardware', 'Available', 'N/A'],
                ['Five', 'ACME', 'Hardware', 'Available', 'NA'],
            ],
        );

        $this->assertSame(3, $batch->success_count); // Two, Four, Five (NA serials are blank)
        $this->assertNotEmpty($this->errorsFor($batch, 2));
        $this->assertStringContainsString('already used by asset', json_encode($this->errorsFor($batch, 4)));
    }

    public function test_unique_custom_field_is_enforced_inside_the_file(): void
    {
        CategoryField::create([
            'category_id' => $this->category->id, 'field_key' => 'mac_id', 'label' => 'MAC ID',
            'field_type' => 'text', 'is_required' => false, 'display_order' => 1, 'is_active' => true, 'is_unique' => true,
        ]);

        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status', 'mac_id'],
            [
                ['One', 'ACME', 'Hardware', 'Available', 'AA:BB'],
                ['Two', 'ACME', 'Hardware', 'Available', 'aa:bb'],
            ],
        );

        $this->assertSame(1, $batch->success_count);
        // Row 2 is saved before row 3 is read, so the case-insensitive DB check catches it.
        $this->assertStringContainsString('already used by asset', json_encode($this->errorsFor($batch, 3)));
    }

    public function test_active_creation_workflow_creates_drafts_under_one_batch_approval(): void
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::create(['name' => 'Creation', 'module' => 'asset_creation', 'is_active' => true]);
        $workflow->steps()->create(['level' => 1, 'approver_type' => 'role', 'approver_role' => 'Approver', 'escalation_hours' => 48]);

        $batch = $this->import(
            ['name', 'company_code', 'asset_type', 'status'],
            [['One', 'ACME', 'Hardware', 'Available'], ['Two', 'ACME', 'Hardware', 'Available']],
        );

        $this->assertSame('pending', $batch->approval_status);
        $this->assertSame(1, ApprovalRequest::count(), 'one approval for the whole batch');
        $draftId = AssetStatus::where('code', 'DRAFT')->value('id');
        $this->assertSame(2, Asset::where('status_id', $draftId)->count());
        $this->assertTrue(Asset::first()->hasPendingCreationApproval());

        $approver = User::factory()->create();
        $approver->assignRole('Approver');
        app(WorkflowService::class)->approve(ApprovalRequest::first(), $approver);

        $this->assertSame('approved', $batch->fresh()->approval_status);
        $availableId = AssetStatus::where('code', 'AVAILABLE')->value('id');
        $this->assertSame(2, Asset::where('status_id', $availableId)->count());
    }

    public function test_template_uses_names_and_has_a_hidden_lists_sheet(): void
    {
        $template = new AssetTemplateExport($this->category->id, app(DynamicFieldService::class));

        $this->assertContains('asset_type', $template->headings());
        $this->assertContains('custodian', $template->headings());
        $this->assertNotContains('status_id', $template->headings());
        $this->assertCount(2, $template->sheets());
    }
}
