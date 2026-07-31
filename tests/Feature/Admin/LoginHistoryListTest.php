<?php

namespace Tests\Feature\Admin;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LoginHistoryListTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private string $url = '/admin/login-history';

    // ── Access control ─────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url)->assertRedirect('/login');
    }

    public function test_super_admin_can_view_login_history(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
            ->get($this->url)
            ->assertStatus(200);
    }

    public function test_viewer_without_permission_gets_403(): void
    {
        $viewer = $this->createUserWithRole('Viewer');

        $this->actingAs($viewer)
            ->get($this->url)
            ->assertStatus(403);
    }

    public function test_asset_manager_without_login_history_permission_gets_403(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
            ->get($this->url)
            ->assertStatus(403);
    }

    // ── Data display ───────────────────────────────────────────────────────

    public function test_login_history_list_shows_records(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $user  = User::factory()->create();

        LoginHistory::create([
            'user_id'     => $user->id,
            'email'       => $user->email,
            'ip_address'  => '192.168.1.1',
            'user_agent'  => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'status'      => 'success',
            'created_at'  => now(),
        ]);

        $this->actingAs($admin)
            ->get($this->url)
            ->assertStatus(200)
            ->assertSee($user->email)
            ->assertSee('192.168.1.1');
    }

    public function test_empty_state_shows_no_records_message(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
            ->get($this->url)
            ->assertStatus(200)
            ->assertSee('No login records found');
    }

    // ── Filtering ──────────────────────────────────────────────────────────

    public function test_filter_by_status_success(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $user  = User::factory()->create();

        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'ip_address' => '1.1.1.1', 'device_type' => 'desktop', 'status' => 'success', 'created_at' => now()]);
        LoginHistory::create(['user_id' => null, 'email' => 'bad@x.com', 'ip_address' => '2.2.2.2', 'device_type' => 'desktop', 'status' => 'failed', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get("{$this->url}?status=success");

        $response->assertStatus(200)
            ->assertSee($user->email)
            ->assertDontSee('bad@x.com');
    }

    public function test_filter_by_status_failed(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $user  = User::factory()->create();

        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'ip_address' => '1.1.1.1', 'device_type' => 'desktop', 'status' => 'success', 'created_at' => now()]);
        LoginHistory::create(['user_id' => null, 'email' => 'bad@x.com', 'ip_address' => '2.2.2.2', 'device_type' => 'desktop', 'status' => 'failed', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get("{$this->url}?status=failed");

        $response->assertStatus(200)
            ->assertSee('bad@x.com')
            ->assertDontSee($user->email);
    }

    public function test_filter_by_date_range(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        LoginHistory::create(['user_id' => null, 'email' => 'old@x.com', 'ip_address' => '1.1.1.1', 'device_type' => 'desktop', 'status' => 'failed', 'created_at' => now()->subDays(10)]);
        LoginHistory::create(['user_id' => null, 'email' => 'new@x.com', 'ip_address' => '2.2.2.2', 'device_type' => 'desktop', 'status' => 'failed', 'created_at' => now()]);

        $response = $this->actingAs($admin)->get("{$this->url}?date_from=" . now()->subDays(1)->toDateString());

        $response->assertStatus(200)
            ->assertSee('new@x.com')
            ->assertDontSee('old@x.com');
    }

    // ── Pagination ─────────────────────────────────────────────────────────

    public function test_list_is_paginated(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        for ($i = 0; $i < 55; $i++) {
            LoginHistory::create([
                'user_id'     => null,
                'email'       => "user{$i}@example.com",
                'ip_address'  => '127.0.0.1',
                'device_type' => 'desktop',
                'status'      => 'failed',
                'created_at'  => now()->subSeconds($i),
            ]);
        }

        $response = $this->actingAs($admin)->get($this->url);
        $response->assertStatus(200);

        // 55 records, 50 per page — second page should exist
        $this->actingAs($admin)->get("{$this->url}?page=2")->assertStatus(200);
    }
}
