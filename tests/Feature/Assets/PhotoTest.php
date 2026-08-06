<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_store_uploads_photo_and_sets_first_as_primary(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.photos.store', $asset), [
                 'photos' => [UploadedFile::fake()->image('photo.jpg')],
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('asset_photos', ['asset_id' => $asset->id, 'is_primary' => true]);
    }

    public function test_store_rejects_oversized_photo(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.photos.store', $asset), [
                 'photos' => [UploadedFile::fake()->image('big.jpg')->size(10241)], // > 10 MB
             ])
             ->assertSessionHasErrors('photos.0');
    }

    public function test_destroy_removes_photo_and_promotes_next_primary(): void
    {
        $asset = Asset::factory()->create();
        $primary = $asset->photos()->create(['path' => 'assets/1/photos/a.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $secondary = $asset->photos()->create(['path' => 'assets/1/photos/b.jpg', 'is_primary' => false, 'sort_order' => 1]);

        $this->actingAs($this->admin())
             ->delete(route('assets.photos.destroy', [$asset, $primary]))
             ->assertRedirect();

        $this->assertDatabaseMissing('asset_photos', ['id' => $primary->id]);
        $this->assertTrue($secondary->refresh()->is_primary);
    }

    public function test_set_primary_updates_flags(): void
    {
        $asset = Asset::factory()->create();
        $photoA = $asset->photos()->create(['path' => 'a.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $photoB = $asset->photos()->create(['path' => 'b.jpg', 'is_primary' => false, 'sort_order' => 1]);

        $this->actingAs($this->admin())
             ->patch(route('assets.photos.primary', [$asset, $photoB]))
             ->assertRedirect();

        $this->assertFalse($photoA->refresh()->is_primary);
        $this->assertTrue($photoB->refresh()->is_primary);
    }

    public function test_store_requires_assets_edit_permission(): void
    {
        $this->seedRolesAndPermissions();
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('assets.view');
        $asset = Asset::factory()->create();

        $this->actingAs($user)
             ->post(route('assets.photos.store', $asset), [
                 'photos' => [UploadedFile::fake()->image('photo.jpg')],
             ])
             ->assertForbidden();
    }
}
