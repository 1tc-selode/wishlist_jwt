<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Teszt User',
            'email' => 'teszt@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'is_admin']]);
    }

    /** @test */
    public function user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'teszt@example.com',
            'password' => bcrypt('password'),
        ]);
        $response = $this->postJson('/api/login', [
            'email' => 'teszt@example.com',
            'password' => 'password',
        ]);
        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'user', 'access_token', 'token_type', 'expires_in']);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/logout');
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully']);
    }
}
