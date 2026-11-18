<?php

namespace Tests\Feature;

use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * A dashboard card's link must open exactly the assets its number counts — the "Expiring
 * Warranties / AMC (30d)" cards used to open the whole, unfiltered asset list.
 */
class DashboardCardLinksTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_expiring_cards_link_to_a_list_of_exactly_the_counted_assets(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        Asset::factory()->create(['name' => 'WARRANTY-SOON', 'warranty_expiry' => now()->addDays(10)]);
        Asset::factory()->create(['name' => 'WARRANTY-LATER', 'warranty_expiry' => now()->addDays(90)]);
        Asset::factory()->create(['name' => 'AMC-SOON', 'amc_expiry' => now()->addDays(5)]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('assets.index', ['expiring' => 'warranty']), false)
            ->assertSee(route('assets.index', ['expiring' => 'amc']), false);

        $this->actingAs($admin)->get(route('assets.index', ['expiring' => 'warranty']))
            ->assertOk()
            ->assertSee('WARRANTY-SOON')
            ->assertDontSee('WARRANTY-LATER')
            ->assertDontSee('AMC-SOON')
            ->assertSee('expires in the next 30 days');

        $this->actingAs($admin)->get(route('assets.index', ['expiring' => 'amc']))
            ->assertOk()->assertSee('AMC-SOON')->assertDontSee('WARRANTY-SOON');

        // An unknown value is ignored rather than breaking the list.
        $this->actingAs($admin)->get(route('assets.index', ['expiring' => 'bogus']))->assertOk()->assertSee('WARRANTY-LATER');
    }
}
