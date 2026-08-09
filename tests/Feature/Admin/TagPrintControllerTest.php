<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TagPrintControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_pdf_requires_tags_print_permission(): void
    {
        $user = $this->createUserWithRole('Department User');
        $this->actingAs($user)
             ->get(route('admin.tags.print.pdf'))
             ->assertForbidden();
    }

    public function test_pdf_downloads_available_tags_by_default(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        Tag::factory()->available()->count(2)->create();

        $response = $this->actingAs($manager)->get(route('admin.tags.print.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function test_pdf_downloads_tags_for_an_explicit_batch(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $batch = app(\App\Services\TagService::class)->generateBatch(3, $manager);

        $response = $this->actingAs($manager)->get(route('admin.tags.print.pdf', ['batch' => $batch->id]));

        $response->assertOk();
        $this->assertNotEmpty($response->getContent());
    }

    public function test_word_requires_tags_print_permission(): void
    {
        $user = $this->createUserWithRole('Viewer');
        $this->actingAs($user)
             ->get(route('admin.tags.print.word'))
             ->assertForbidden();
    }

    public function test_word_downloads_a_docx_with_a_non_empty_body(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        Tag::factory()->available()->count(2)->create();
        Tag::factory()->barcode()->available()->count(2)->create();

        $response = $this->actingAs($manager)->get(route('admin.tags.print.word'));

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('content-type'),
        );
        $this->assertNotEmpty($response->streamedContent());
    }
}
