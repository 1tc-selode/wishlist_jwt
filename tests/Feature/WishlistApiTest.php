<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Tymon\JWTAuth\Facades\JWTAuth;

class WishlistApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/wishlists');
        $response->assertStatus(200)
                 ->assertJsonFragment(['product_id' => $product->id]);
    }

    /** @test */
    public function user_can_add_and_remove_product_from_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        $add = $this->withHeader('Authorization', 'Bearer ' . $token)
                     ->postJson('/api/wishlists', ['product_id' => $product->id]);
        $add->assertStatus(201)
            ->assertJsonStructure(['message', 'wishlist']);
        $wishlistId = $add->json('wishlist.id');
        $remove = $this->withHeader('Authorization', 'Bearer ' . $token)
                       ->deleteJson('/api/wishlists/' . $wishlistId);
        $remove->assertStatus(200)
               ->assertJson(['message' => 'Product removed from wishlist (soft delete)']);
    }

    /** @test */
    public function admin_can_list_all_wishlists_and_user_wishlist()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        $all = $this->withHeader('Authorization', 'Bearer ' . $token)
                    ->getJson('/api/admin/wishlists');
        $all->assertStatus(200)
            ->assertJsonFragment(['user_id' => $user->id]);
        $userList = $this->withHeader('Authorization', 'Bearer ' . $token)
                        ->getJson('/api/admin/users/' . $user->id . '/wishlists');
        $userList->assertStatus(200)
                 ->assertJsonFragment(['product_id' => $product->id]);
    }
}
