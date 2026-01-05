<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_list_and_manage_users()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        $list = $this->withHeader('Authorization', 'Bearer ' . $token)
                     ->getJson('/api/users');
        $list->assertStatus(200)
             ->assertJsonFragment(['id' => $user->id]);
        $show = $this->withHeader('Authorization', 'Bearer ' . $token)
                     ->getJson('/api/users/' . $user->id);
        $show->assertStatus(200)
             ->assertJson(['id' => $user->id]);
        $update = $this->withHeader('Authorization', 'Bearer ' . $token)
                       ->putJson('/api/users/' . $user->id, ['name' => 'Updated']);
        $update->assertStatus(200)
               ->assertJson(['user' => ['name' => 'Updated']]);
        $delete = $this->withHeader('Authorization', 'Bearer ' . $token)
                       ->deleteJson('/api/users/' . $user->id);
        $delete->assertStatus(200)
               ->assertJson(['message' => 'User deleted successfully (soft delete)']);
    }
}
