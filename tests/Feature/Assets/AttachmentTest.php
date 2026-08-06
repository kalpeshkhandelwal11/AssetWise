<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AttachmentTest extends TestCase
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

    public function test_store_uploads_attachment_with_type(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.attachments.store', $asset), [
                 'type' => 'invoice',
                 'file' => UploadedFile::fake()->create('invoice.pdf', 500, 'application/pdf'),
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('asset_attachments', [
            'asset_id' => $asset->id,
            'type' => 'invoice',
            'original_name' => 'invoice.pdf',
        ]);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.attachments.store', $asset), [
                 'type' => 'not-a-real-type',
                 'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
             ])
             ->assertSessionHasErrors('type');
    }

    public function test_store_rejects_oversized_file(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin())
             ->post(route('assets.attachments.store', $asset), [
                 'type' => 'manual',
                 'file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf'), // > 20 MB
             ])
             ->assertSessionHasErrors('file');
    }

    public function test_destroy_removes_attachment(): void
    {
        $asset = Asset::factory()->create();
        $attachment = $asset->attachments()->create([
            'type' => 'manual', 'path' => 'assets/1/attachments/manual.pdf',
            'original_name' => 'manual.pdf', 'mime' => 'application/pdf', 'size' => 1000,
        ]);

        $this->actingAs($this->admin())
             ->delete(route('assets.attachments.destroy', [$asset, $attachment]))
             ->assertRedirect();

        $this->assertDatabaseMissing('asset_attachments', ['id' => $attachment->id]);
    }
}
