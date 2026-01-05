<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function anyone_can_list_products()
    {
        Product::factory()->count(3)->create();
        $response = $this->getJson('/api/products');
        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    /** @test */
    public function anyone_can_view_a_product()
    {
        $product = Product::factory()->create();
        $response = $this->getJson('/api/products/' . $product->id);
        $response->assertStatus(200)
                 ->assertJson(['id' => $product->id]);
    }

    /** @test */
    public function admin_can_create_update_delete_product()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $data = [
            'name' => 'Teszt Termék',
            'category' => 'Teszt',
            'price' => 123.45,
            'stock' => 10,
        ];
        $create = $this->withHeader('Authorization', 'Bearer ' . $token)
                      ->postJson('/api/products', $data);
        $create->assertStatus(201)
               ->assertJsonStructure(['message', 'product']);
        $productId = $create->json('product.id');
        $update = $this->withHeader('Authorization', 'Bearer ' . $token)
                      ->putJson('/api/products/' . $productId, ['stock' => 99]);
        $update->assertStatus(200)
               ->assertJson(['product' => ['stock' => 99]]);
        $delete = $this->withHeader('Authorization', 'Bearer ' . $token)
                      ->deleteJson('/api/products/' . $productId);
        $delete->assertStatus(200)
               ->assertJson(['message' => 'Product deleted successfully (soft delete)']);
    }
}
