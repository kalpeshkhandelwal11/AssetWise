<?php

namespace Tests\Unit\Imports;

use App\Imports\AssetImport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\User;
use App\Services\AssetService;
use App\Services\DynamicFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeBatch(AssetCategory $category, User $user): ImportBatch
    {
        return ImportBatch::create([
            'category_id' => $category->id,
            'user_id'     => $user->id,
            'filename'    => 'test.xlsx',
            'status'      => 'processing',
        ]);
    }

    private function writeSpreadsheet(array $headings, array $rows): string
    {
        Storage::fake('local');

        $export = new class($headings, $rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $headings, private array $rows)
            {
            }

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

        return $path;
    }

    public function test_valid_row_creates_asset_and_field_values(): void
    {
        $company = Company::factory()->create(['code' => 'ACME', 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        CategoryField::create([
            'category_id' => $category->id,
            'field_key'   => 'ram_gb',
            'label'       => 'RAM (GB)',
            'field_type'  => 'number',
            'is_required' => true,
            'is_active'   => true,
        ]);
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create();
        $batch = $this->makeBatch($category, $user);

        $headings = ['name', 'company_code', 'asset_type_id', 'status_id', 'ram_gb'];
        $path = $this->writeSpreadsheet($headings, [
            ['Test Laptop', 'ACME', $type->id, $status->id, 16],
        ]);

        Excel::import(new AssetImport($batch, $user, app(AssetService::class), app(DynamicFieldService::class)), $path, 'local');

        $this->assertSame(1, Asset::count());
        $asset = Asset::first();
        $this->assertSame('Test Laptop', $asset->name);
        $this->assertSame($company->id, $asset->company_id);
        $this->assertSame($category->id, $asset->category_id);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id'     => $asset->id,
            'value_number' => 16,
        ]);

        $this->assertSame(1, ImportBatchRow::where('status', 'success')->count());
        $row = ImportBatchRow::where('status', 'success')->first();
        $this->assertSame($asset->id, $row->asset_id);
    }

    public function test_invalid_company_code_is_reported_on_its_row_without_aborting_the_batch(): void
    {
        Company::factory()->create(['code' => 'ACME', 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create();
        $batch = $this->makeBatch($category, $user);

        $headings = ['name', 'company_code', 'asset_type_id', 'status_id'];
        $path = $this->writeSpreadsheet($headings, [
            ['Good Asset', 'ACME', $type->id, $status->id],
            ['Bad Company Asset', 'NOPE', $type->id, $status->id],
        ]);

        Excel::import(new AssetImport($batch, $user, app(AssetService::class), app(DynamicFieldService::class)), $path, 'local');

        $this->assertSame(1, Asset::count());
        $this->assertSame(1, ImportBatchRow::where('status', 'success')->count());
        $this->assertSame(1, ImportBatchRow::where('status', 'failed')->count());

        $failedRow = ImportBatchRow::where('status', 'failed')->first();
        $this->assertArrayHasKey('company_code', $failedRow->errors);
        $this->assertStringContainsString('NOPE', $failedRow->errors['company_code'][0]);
    }

    public function test_missing_required_dynamic_field_is_reported_with_a_specific_message(): void
    {
        Company::factory()->create(['code' => 'ACME', 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        CategoryField::create([
            'category_id' => $category->id,
            'field_key'   => 'ram_gb',
            'label'       => 'RAM (GB)',
            'field_type'  => 'number',
            'is_required' => true,
            'is_active'   => true,
        ]);
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create();
        $batch = $this->makeBatch($category, $user);

        $headings = ['name', 'company_code', 'asset_type_id', 'status_id', 'ram_gb'];
        $path = $this->writeSpreadsheet($headings, [
            ['No RAM Asset', 'ACME', $type->id, $status->id, null],
        ]);

        Excel::import(new AssetImport($batch, $user, app(AssetService::class), app(DynamicFieldService::class)), $path, 'local');

        $this->assertSame(0, Asset::count());
        $failedRow = ImportBatchRow::where('status', 'failed')->first();
        $this->assertNotNull($failedRow);
        $this->assertArrayHasKey('fields.ram_gb', $failedRow->errors);
    }

    public function test_batch_continues_past_bad_rows_and_imports_every_valid_row(): void
    {
        Company::factory()->create(['code' => 'ACME', 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create();
        $batch = $this->makeBatch($category, $user);

        $headings = ['name', 'company_code', 'asset_type_id', 'status_id'];
        $path = $this->writeSpreadsheet($headings, [
            ['Asset One', 'ACME', $type->id, $status->id],
            ['Asset Two', 'BAD', $type->id, $status->id],
            ['Asset Three', 'ACME', $type->id, $status->id],
        ]);

        Excel::import(new AssetImport($batch, $user, app(AssetService::class), app(DynamicFieldService::class)), $path, 'local');

        $this->assertSame(2, Asset::count());
        $this->assertSame(2, ImportBatchRow::where('status', 'success')->count());
        $this->assertSame(1, ImportBatchRow::where('status', 'failed')->count());
    }
}
