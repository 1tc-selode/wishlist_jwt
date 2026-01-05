<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Laptop Dell XPS 15',
                'category' => 'Electronics',
                'price' => 1299.99,
                'stock' => 15,
            ],
            [
                'name' => 'iPhone 15 Pro',
                'category' => 'Electronics',
                'price' => 999.99,
                'stock' => 25,
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'category' => 'Electronics',
                'price' => 899.99,
                'stock' => 30,
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'category' => 'Audio',
                'price' => 349.99,
                'stock' => 50,
            ],
            [
                'name' => 'Apple Watch Series 9',
                'category' => 'Wearables',
                'price' => 399.99,
                'stock' => 40,
            ],
            [
                'name' => 'iPad Air',
                'category' => 'Tablets',
                'price' => 599.99,
                'stock' => 20,
            ],
            [
                'name' => 'Mechanical Keyboard',
                'category' => 'Accessories',
                'price' => 129.99,
                'stock' => 60,
            ],
            [
                'name' => 'Gaming Mouse Logitech',
                'category' => 'Accessories',
                'price' => 79.99,
                'stock' => 100,
            ],
            [
                'name' => '4K Monitor 27"',
                'category' => 'Electronics',
                'price' => 449.99,
                'stock' => 12,
            ],
            [
                'name' => 'External SSD 1TB',
                'category' => 'Storage',
                'price' => 99.99,
                'stock' => 75,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
