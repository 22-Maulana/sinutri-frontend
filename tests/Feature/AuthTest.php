<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\ActivationMail;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully()
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Automated Test User',
            'email' => 'autotest@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'requires_activation' => true,
                     'email' => 'autotest@example.com',
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'autotest@example.com',
            'is_active' => false,
        ]);

        Mail::assertSent(ActivationMail::class);
    }

    public function test_user_register_fails_validation()
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors']);
    }

    public function test_user_can_verify_otp()
    {
        $user = User::create([
            'name' => 'OTP User',
            'email' => 'otp@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'user',
            'is_active' => false,
            'otp_code' => '123456',
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'email' => 'otp@example.com',
            'otp_code' => '123456',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'otp@example.com',
            'is_active' => true,
            'otp_code' => null,
        ]);
    }

    public function test_user_login_requires_activation()
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'user',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'requires_activation' => true,
                 ]);
    }

    public function test_active_user_can_login()
    {
        $user = User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'user',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);
    }
}
