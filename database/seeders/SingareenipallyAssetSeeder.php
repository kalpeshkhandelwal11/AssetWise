<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Building;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Room;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SingareenipallyAssetSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Wipe previous run so re-seeding is safe ──────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $assetIds = DB::table('assets')
            ->whereIn('company_id', fn($q) => $q->select('id')->from('companies')->where('code', 'SFPA'))
            ->pluck('id');

        if ($assetIds->isNotEmpty()) {
            DB::table('asset_status_histories')->whereIn('asset_id', $assetIds)->delete();
            DB::table('asset_movements')->whereIn('asset_id', $assetIds)->delete();
            DB::table('asset_tag_assignments')->whereIn('asset_id', $assetIds)->delete();
            DB::table('asset_field_values')->whereIn('asset_id', $assetIds)->delete();
            DB::table('asset_photos')->whereIn('asset_id', $assetIds)->delete();
            DB::table('asset_attachments')->whereIn('asset_id', $assetIds)->delete();
            DB::table('disposal_requests')->whereIn('asset_id', $assetIds)->delete();
            DB::table('assets')->whereIn('id', $assetIds)->delete();
        }

        Room::whereIn('code', ['SFPA_MESS','SFPA_OFFICE','SFPA_MGR','SFPA_PANTRY','SFPA_LANE'])->delete();

        $floorIds = Floor::whereIn('code', ['SFPA_GF'])->pluck('id');
        Floor::whereIn('id', $floorIds)->delete();

        $buildingIds = Building::whereIn('code', ['SFPA_BLDG'])->pluck('id');
        Building::whereIn('id', $buildingIds)->delete();

        Location::where('code', 'SFPA_SITE')->delete();
        Company::where('code', 'SFPA')->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Company ───────────────────────────────────────────────────────────
        $company = Company::create([
            'code'          => 'SFPA',
            'name'          => 'Singarenipally Fee Plaza',
            'city'          => 'Singarenipally',
            'country'       => 'India',
            'contact_email' => 'info@sfpa.in',
            'is_active'     => true,
        ]);

        // ── Location hierarchy ────────────────────────────────────────────────
        // Location → Building (single) → Floor (ground) → Rooms
        $location = Location::firstOrCreate(['code' => 'SFPA_SITE'], [
            'name'    => 'Singarenipally Fee Plaza',
            'address' => 'Singarenipally, Telangana',
            'is_active' => true,
        ]);

        $building = Building::firstOrCreate(
            ['location_id' => $location->id, 'code' => 'SFPA_BLDG'],
            ['name' => 'Fee Plaza', 'is_active' => true]
        );

        $floor = Floor::firstOrCreate(
            ['building_id' => $building->id, 'code' => 'SFPA_GF'],
            ['name' => 'Ground Floor', 'is_active' => true]
        );

        $rooms = [];
        foreach ([
            'SFPA_MESS'    => 'Mess',
            'SFPA_OFFICE'  => 'Office',
            'SFPA_MGR'     => 'Manager Room',
            'SFPA_PANTRY'  => 'Pantry Room',
            'SFPA_LANE'    => 'Lane',
        ] as $code => $name) {
            $rooms[$code] = Room::firstOrCreate(
                ['floor_id' => $floor->id, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $mess   = $rooms['SFPA_MESS']->id;
        $office = $rooms['SFPA_OFFICE']->id;
        $pantry = $rooms['SFPA_PANTRY']->id;
        $lane   = $rooms['SFPA_LANE']->id;

        // ── Categories ────────────────────────────────────────────────────────
        $messEquip = AssetCategory::firstOrCreate(['code' => 'MESS_EQUIPMENT'], [
            'name' => 'Mess Equipment', 'is_active' => true, 'sort_order' => 10,
        ]);
        $vehicles = AssetCategory::firstOrCreate(['code' => 'VEHICLES'], [
            'name' => 'Vehicles', 'is_active' => true, 'sort_order' => 11,
        ]);
        $officeSupplies = AssetCategory::firstOrCreate(['code' => 'OFFICE_SUPPLIES'], [
            'name' => 'Office Supplies', 'is_active' => true, 'sort_order' => 12,
        ]);

        // ── Lookup IDs ────────────────────────────────────────────────────────
        $typeId = fn(string $code) => DB::table('asset_types')->where('code', $code)->value('id');
        $equipment  = $typeId('EQUIPMENT');
        $vehicle    = $typeId('VEHICLE');
        $furniture  = $typeId('FURNITURE');
        $consumable = $typeId('CONSUMABLE');

        $statusId = DB::table('asset_statuses')->where('code', 'AVAILABLE')->value('id');
        $adminId  = DB::table('users')->where('email', 'admin@assetwise.test')->value('id');
        $date     = '2026-05-20';

        // ── Asset factory closure ─────────────────────────────────────────────
        $make = function (int $qty, string $name, int $catId, int $assetTypeId, int $roomId, ?float $unitCost, ?string $serial = null, ?string $notes = null)
            use ($company, $location, $building, $floor, $statusId, $adminId, $date)
        {
            for ($i = 1; $i <= $qty; $i++) {
                Asset::create([
                    'name'          => $qty > 1 ? "$name #$i" : $name,
                    'company_id'    => $company->id,
                    'category_id'   => $catId,
                    'asset_type_id' => $assetTypeId,
                    'status_id'     => $statusId,
                    'location_id'   => $location->id,
                    'building_id'   => $building->id,
                    'floor_id'      => $floor->id,
                    'room_id'       => $roomId,
                    'purchase_date' => $date,
                    'purchase_cost' => $unitCost,
                    'serial_number' => $serial,
                    'notes'         => $notes,
                    'is_active'     => true,
                    'created_by'    => $adminId,
                ]);
            }
        };

        // ── Section 1: Current Equipment Status (Handover — Riddhi Siddhi) ───

        $make(15, 'Gadda',             $messEquip->id, $consumable, $mess,   240.00, null, '3 pcs newly purchased (Rs 1,800)');
        $make(9,  'Pilo',              $messEquip->id, $consumable, $mess,    53.33, null, '3 pcs newly purchased (Rs 900)');
        $make(2,  'Fan',               $messEquip->id, $equipment,  $mess,   675.00, null, '1 nos brought from Pantangi Toll Plaza');
        $make(2,  'Gas Cylinder',      $messEquip->id, $equipment,  $mess,  1350.00, null, '1 nos brought from Pantangi Toll Plaza');
        $make(2,  'Gas Chulha',        $messEquip->id, $equipment,  $mess,  1000.00);
        $make(1,  'Mixer Grinder',     $messEquip->id, $equipment,  $mess,  2099.00, null, 'New purchase');
        $make(1,  'Tawa Roti',         $messEquip->id, $consumable, $mess,   100.00);
        $make(1,  'Belon',             $messEquip->id, $consumable, $mess,   100.00);
        $make(1,  'Chakti',            $messEquip->id, $consumable, $mess,   100.00);
        $make(1,  'Steel Chimta',      $messEquip->id, $consumable, $mess,   100.00);
        $make(2,  'Steel Can',         $messEquip->id, $consumable, $mess,   675.00);
        $make(1,  'Tasla Roti Silver', $messEquip->id, $consumable, $mess,   650.00);
        $make(2,  'Kadai',             $messEquip->id, $consumable, $mess,   300.00);
        $make(10, 'Plate',             $messEquip->id, $consumable, $mess,   240.00);
        $make(2,  'Suspen',            $messEquip->id, $equipment,  $mess,   200.00);
        $make(1,  'Cooker',            $messEquip->id, $equipment,  $mess,   850.00);
        $make(1,  'Bhagona Silver',    $messEquip->id, $consumable, $mess,   850.00);
        $make(3,  'Chamcha',           $messEquip->id, $consumable, $mess,   116.67);
        $make(1,  'Drying Rack',       $messEquip->id, $equipment,  $mess,   400.00);
        $make(1,  'Induction Cooker',  $messEquip->id, $equipment,  $pantry, 2100.00);
        $make(1,  'Steel Lota',        $messEquip->id, $consumable, $pantry,  150.00);
        $make(1,  'Steel Bhagona',     $messEquip->id, $consumable, $pantry,  400.00);

        // ── Section 2: New Items ──────────────────────────────────────────────

        $make(1, 'Scooty',                  $vehicles->id,       $vehicle,    $office,  null,   'AP40HK7382');
        $make(3, 'Lunch Box',               $officeSupplies->id, $consumable, $office,  450.00);
        $make(3, 'Fibre Plate',             $officeSupplies->id, $consumable, $office,   75.00);
        $make(3, 'Bowl',                    $officeSupplies->id, $consumable, $office,   45.00);
        $make(3, 'Bucket',                  $officeSupplies->id, $consumable, $office,  280.00);
        $make(1, 'Tea Cup Set',             $officeSupplies->id, $consumable, $office,  320.00);
        $make(1, 'Glass Set',               $officeSupplies->id, $consumable, $office,  280.00);
        $make(1, 'Fibre Tray',              $officeSupplies->id, $consumable, $office,  160.00);
        $make(1, 'Pliers',                  $officeSupplies->id, $equipment,  $office,  195.00);
        $make(1, 'Tester',                  $officeSupplies->id, $equipment,  $office,  195.00);
        $make(3, 'Earthen Water Dispenser', $officeSupplies->id, $equipment,  $lane,    270.00);
        $make(3, 'Cool Water Can',          $officeSupplies->id, $consumable, $mess,    600.00);
        $make(1, 'Water Can (20 Ltr)',      $officeSupplies->id, $consumable, $mess,    220.00);
        $make(4, 'Umbrella',                $officeSupplies->id, $consumable, $lane,    240.00);
        $make(2, 'Chair',                   $officeSupplies->id, $furniture,  $lane,    450.00);
        $make(1, 'Flasko',                  $officeSupplies->id, $consumable, $pantry,  599.00);
    }
}
