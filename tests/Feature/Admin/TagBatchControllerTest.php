<?php

namespace Tests\Feature\Admin;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class TagBatchControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.tags.index'))->assertRedirect('/login');
    }

    public function test_index_requires_tags_view_permission(): void
    {
        $user = $this->createUserWithRole('Department User'); // no tags.view
        $this->actingAs($user)
             ->get(route('admin.tags.index'))
             ->assertForbidden();
    }

    public function test_index_lists_tags_and_filters_by_status(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $available = Tag::factory()->available()->create();
        $inactive = Tag::factory()->inactive()->create();

        $this->actingAs($manager)
             ->get(route('admin.tags.index', ['status' => 'available']))
             ->assertOk()
             ->assertSee($available->tag_number)
             ->assertDontSee($inactive->tag_number);
    }

    public function test_create_form_requires_tags_generate_permission(): void
    {
        $user = $this->createUserWithRole('Auditor'); // no tags.generate
        $this->actingAs($user)
             ->get(route('admin.tags.batches.create'))
             ->assertForbidden();
    }

    public function test_store_generates_a_batch(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
             ->post(route('admin.tags.batches.store'), ['quantity' => 5])
             ->assertRedirect(route('admin.tags.index'))
             ->assertSessionHas('success');

        $this->assertDatabaseCount('tags', 5);
        $this->assertDatabaseCount('tag_batches', 1);
    }

    public function test_store_validates_quantity(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
             ->post(route('admin.tags.batches.store'), ['quantity' => 0])
             ->assertSessionHasErrors('quantity');
    }

    public function test_store_forbidden_without_tags_generate_permission(): void
    {
        $user = $this->createUserWithRole('Viewer');

        $this->actingAs($user)
             ->post(route('admin.tags.batches.store'), ['quantity' => 5])
             ->assertForbidden();
    }
}
