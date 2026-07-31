<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'Admin@1234',
                'password'              => 'NewPass@9876',
                'password_confirmation' => 'NewPass@9876',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('NewPass@9876', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'WrongPass@1',
                'password'              => 'NewPass@9876',
                'password_confirmation' => 'NewPass@9876',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_new_password_must_pass_strength_policy(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'Admin@1234',
                'password'              => 'weakpass',
                'password_confirmation' => 'weakpass',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'password')
            ->assertRedirect('/profile');
    }
}
