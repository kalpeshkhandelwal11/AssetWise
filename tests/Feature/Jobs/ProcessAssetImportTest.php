<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAssetImport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProcessAssetImportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_handle_updates_batch_counts_and_notifies_the_uploader(): void
    {
        $company = Company::factory()->create(['code' => 'ACME', 'is_active' => true]);
        $category = AssetCategory::factory()->create();
        $type = AssetType::create(['name' => 'Hardware', 'code' => 'HW', 'is_active' => true]);
        $status = AssetStatus::create(['name' => 'Available', 'code' => 'AVAILABLE', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
        $user = User::factory()->create();

        $batch = ImportBatch::create([
            'category_id' => $category->id,
            'user_id'     => $user->id,
            'filename'    => 'assets.xlsx',
            'status'      => 'processing',
        ]);

        $headings = ['name', 'company_code', 'asset_type_id', 'status_id'];
        $path = $this->writeSpreadsheet($headings, [
            ['Good Asset', 'ACME', $type->id, $status->id],
            ['Bad Company Asset', 'NOPE', $type->id, $status->id],
        ]);

        (new ProcessAssetImport($batch->id, $path))->handle(
            app(\App\Services\AssetService::class),
            app(\App\Services\DynamicFieldService::class),
            app(\App\Services\NotificationService::class),
        );

        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->success_count);
        $this->assertSame(1, $batch->error_count);
        $this->assertSame(1, Asset::count());

        $notification = $user->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('import_completed', $notification->type);
        $this->assertSame($batch->id, $notification->data['batch_id']);
        $this->assertSame(1, $notification->data['success_count']);
        $this->assertSame(1, $notification->data['error_count']);
    }
}
