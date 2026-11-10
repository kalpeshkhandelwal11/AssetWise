<?php

namespace Tests\Feature\Nhai;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PlazaTakeover;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\TakeoverService;
use App\Services\WorkflowService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Takeover from NHAI: checklist lines (quantity rows + per-lane serials) expand into NHAI-owned
 * DRAFT assets operated by our company, with no price and no depreciation; one approval puts
 * them live. A purchase takeover is priced, ours and depreciated. Rejection deletes the drafts.
 */
class TakeoverTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $manager;

    private User $approver;

    private Company $ours;

    private Company $nhai;

    private Location $plaza;

    private AssetCategory $furniture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ReferenceDataSeeder::class);

        foreach (['DRAFT' => 'Draft', 'AVAILABLE' => 'Available', 'ASSIGNED' => 'Assigned'] as $code => $name) {
            AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        }

        $this->manager = $this->createUserWithRole('Asset Manager');
        $this->approver = $this->createUserWithRole('Approver');
        $this->ours = Company::factory()->create(['name' => 'Our Toll Co', 'party_type' => 'internal']);
        $this->nhai = Company::where('code', 'NHAI')->firstOrFail();
        $this->plaza = Location::create(['name' => 'Anupalle TP', 'code' => 'ANU', 'is_active' => true, 'is_toll_plaza' => true, 'chainage' => '3+445']);
        $this->plaza->update(['manager_employee_id' => Employee::factory()->create()->id]);

        $method = DepreciationMethod::firstOrCreate(['code' => 'straight_line'], ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true]);
        $this->furniture = AssetCategory::factory()->create(['name' => 'Furniture & Fixtures', 'code' => 'FURN']);
        $this->furniture->depreciationDefault()->create([
            'depreciation_method_id' => $method->id, 'useful_life_months' => 120, 'salvage_percent' => 5, 'start_basis' => 'purchase_date',
        ]);
    }

    private function draft(string $kind): PlazaTakeover
    {
        return app(TakeoverService::class)->createDraft([
            'kind'            => $kind,
            'location_id'     => $this->plaza->id,
            'from_company_id' => $kind === 'nhai' ? $this->nhai->id : Company::factory()->create(['party_type' => 'external_agency'])->id,
            'company_id'      => $this->ours->id,
            'custodian_id'    => $this->plaza->manager_employee_id,
            'takeover_date'   => '2026-04-01',
        ], $this->manager);
    }

    private function approveFully(PlazaTakeover $takeover): void
    {
        $request = ApprovalRequest::findOrFail($takeover->fresh()->approval_request_id);
        app(WorkflowService::class)->approve($request, $this->approver);
        app(WorkflowService::class)->approve($request->fresh(), $this->manager);
    }

    public function test_nhai_takeover_expands_lines_into_nhai_owned_assets(): void
    {
        $takeover = $this->draft('nhai');
        $service = app(TakeoverService::class);

        $service->addLine($takeover, ['item_name' => 'Revolving chair', 'category_id' => $this->furniture->id, 'quantity' => 17, 'condition' => 'functional', 'not_functional_qty' => 2, 'location_note' => 'Toll Manager Room']);
        $service->addLine($takeover, ['item_name' => 'AVC camera', 'category_id' => $this->furniture->id, 'quantity' => 1, 'serial_number' => 'SCQX20526899982', 'position_label' => 'Lane 1 (Booth)', 'condition' => 'not_functional', 'warranty_years' => 5]);

        $service->submit($takeover, $this->manager);

        $assets = $takeover->assets()->get();
        $this->assertCount(18, $assets);
        $this->assertTrue($assets->every(fn ($a) => $a->company_id === $this->nhai->id && $a->operating_company_id === $this->ours->id));
        $this->assertTrue($assets->every(fn ($a) => $a->purchase_cost === null));
        $this->assertSame(2, $assets->where('condition', 'not_functional')->where('serial_number', null)->count());
        $this->assertSame('SCQX20526899982', $assets->firstWhere('serial_number', '!=', null)->serial_number);
        $this->assertSame('2031-04-01', $assets->firstWhere('serial_number', '!=', null)->warranty_expiry->toDateString());
        $this->assertTrue($assets->every(fn ($a) => $a->status->code === 'DRAFT'));

        $this->approveFully($takeover);

        $live = $takeover->assets()->get();
        $this->assertSame('approved', $takeover->fresh()->status);
        $this->assertTrue($live->every(fn ($a) => $a->status->code === 'ASSIGNED')); // custodian = plaza manager
        $this->assertTrue($live->every(fn ($a) => $a->activeDepreciationSetting() === null), 'NHAI assets are never depreciated');
    }

    public function test_nhai_takeover_never_carries_a_price(): void
    {
        $takeover = $this->draft('nhai');

        app(TakeoverService::class)->addLine($takeover, ['item_name' => 'Fan', 'category_id' => $this->furniture->id, 'quantity' => 9, 'unit_price' => 1500, 'condition' => 'functional']);

        $this->assertNull($takeover->lines()->first()->unit_price);
    }

    public function test_purchase_takeover_is_priced_ours_and_depreciated(): void
    {
        $takeover = $this->draft('purchase');
        $service = app(TakeoverService::class);

        try {
            $service->addLine($takeover, ['item_name' => 'Table', 'category_id' => $this->furniture->id, 'quantity' => 2, 'condition' => 'functional']);
            $this->fail('A purchase line needs a price.');
        } catch (\Illuminate\Validation\ValidationException) {
        }

        $service->addLine($takeover, ['item_name' => 'Table', 'category_id' => $this->furniture->id, 'quantity' => 2, 'unit_price' => 4000, 'condition' => 'functional']);
        $service->submit($takeover, $this->manager);
        $this->approveFully($takeover);

        $assets = $takeover->assets()->get();
        $this->assertTrue($assets->every(fn ($a) => $a->company_id === $this->ours->id && (float) $a->purchase_cost === 4000.0));
        $this->assertNotNull($assets->first()->activeDepreciationSetting(), 'purchased assets depreciate');
    }

    public function test_rejection_deletes_the_drafts_and_frees_serials(): void
    {
        $takeover = $this->draft('nhai');
        app(TakeoverService::class)->addLine($takeover, ['item_name' => 'UPS', 'category_id' => $this->furniture->id, 'quantity' => 1, 'serial_number' => 'ZU284D2050', 'condition' => 'functional']);
        app(TakeoverService::class)->submit($takeover, $this->manager);

        $request = ApprovalRequest::findOrFail($takeover->fresh()->approval_request_id);
        app(WorkflowService::class)->reject($request, $this->approver, 'Wrong plaza');

        $this->assertSame('rejected', $takeover->fresh()->status);
        $this->assertSame(0, Asset::withoutGlobalScopes()->withTrashed()->where('serial_number', 'ZU284D2050')->count());

        app(TakeoverService::class)->reopen($takeover->fresh());
        $this->assertSame('draft', $takeover->fresh()->status);
    }

    public function test_checklist_import_is_all_or_nothing(): void
    {
        Storage::fake('local');
        $takeover = $this->draft('nhai');
        $file = function (array $rows) {
            $export = new class($rows) implements FromArray, WithHeadings
            {
                public function __construct(private array $rows) {}

                public function array(): array
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return ['section', 'item_name', 'category', 'quantity', 'serial_number', 'position', 'condition', 'not_functional_qty', 'remarks'];
                }
            };
            $path = 'tmp/'.uniqid().'.xlsx';
            Excel::store($export, $path, 'local');

            return new UploadedFile(Storage::disk('local')->path($path), 'checklist.xlsx', null, null, true);
        };

        $this->actingAs($this->manager)->post(route('nhai.takeovers.import', $takeover), ['file' => $file([
            ['Toll Building', 'Light', 'FURN', 28, '', '', 'Available', 0, ''],
            ['Toll Building', 'Mystery', 'NO-SUCH-CATEGORY', 1, '', '', 'functional', 0, ''],
        ])])->assertSessionHasErrors('file');
        $this->assertSame(0, $takeover->lines()->count());

        $this->actingAs($this->manager)->post(route('nhai.takeovers.import', $takeover), ['file' => $file([
            ['Toll Building', 'Light', 'Furniture & Fixtures', 28, '', '', 'Available', 0, ''],
            ['Hybrid ETC Lanes', 'Barrier', 'FURN', 1, 'SCAIPL/BARRIER/09', 'Lane 1 (Booth)', 'not working', 1, 'Lane closed'],
        ])])->assertSessionHasNoErrors();
        $this->assertSame(29, $takeover->fresh()->totalUnits());
    }

    public function test_screens_and_certificate_render(): void
    {
        $takeover = $this->draft('nhai');
        app(TakeoverService::class)->addLine($takeover, ['item_name' => 'Fan', 'category_id' => $this->furniture->id, 'quantity' => 9, 'condition' => 'functional']);

        $this->actingAs($this->manager)->get(route('nhai.takeovers.create', ['kind' => 'nhai']))->assertOk();
        $this->actingAs($this->manager)->get(route('nhai.takeovers.show', $takeover))->assertOk()->assertSee('Fan');
        $this->actingAs($this->manager)->get(route('nhai.takeovers.certificate', $takeover))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->manager)->get(route('nhai.index', ['tab' => 'takeovers']))->assertOk()->assertSee($takeover->reference_no);
    }
}
