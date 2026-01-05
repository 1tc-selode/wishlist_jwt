<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\Product;

class WishlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // John (user_id: 2) kedvencei
        $john = User::where('email', 'john@example.com')->first();
        
        if ($john) {
            // John kedveli a Laptop-ot, iPhone-t és a Headphone-t
            Wishlist::create([
                'user_id' => $john->id,
                'product_id' => 1, // Laptop Dell XPS 15
                'added_at' => now()->subDays(5),
            ]);

            Wishlist::create([
                'user_id' => $john->id,
                'product_id' => 2, // iPhone 15 Pro
                'added_at' => now()->subDays(3),
            ]);

            Wishlist::create([
                'user_id' => $john->id,
                'product_id' => 4, // Sony WH-1000XM5
                'added_at' => now()->subDays(1),
            ]);

            Wishlist::create([
                'user_id' => $john->id,
                'product_id' => 7, // Mechanical Keyboard
                'added_at' => now(),
            ]);
        }

        // Jane (user_id: 3) kedvencei
        $jane = User::where('email', 'jane@example.com')->first();
        
        if ($jane) {
            // Jane kedveli az Apple Watch-ot, iPad-et és a Monitor-t
            Wishlist::create([
                'user_id' => $jane->id,
                'product_id' => 5, // Apple Watch Series 9
                'added_at' => now()->subDays(7),
            ]);

            Wishlist::create([
                'user_id' => $jane->id,
                'product_id' => 6, // iPad Air
                'added_at' => now()->subDays(4),
            ]);

            Wishlist::create([
                'user_id' => $jane->id,
                'product_id' => 9, // 4K Monitor 27"
                'added_at' => now()->subDays(2),
            ]);

            Wishlist::create([
                'user_id' => $jane->id,
                'product_id' => 3, // Samsung Galaxy S24
                'added_at' => now()->subHours(12),
            ]);

            Wishlist::create([
                'user_id' => $jane->id,
                'product_id' => 10, // External SSD 1TB
                'added_at' => now()->subHours(3),
            ]);
        }

        echo "Wishlist seeder completed successfully!\n";
        echo "- John has " . ($john ? $john->wishlists->count() : 0) . " items in wishlist\n";
        echo "- Jane has " . ($jane ? $jane->wishlists->count() : 0) . " items in wishlist\n";
    }
}
