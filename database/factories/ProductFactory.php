<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word . ' ' . $this->faker->randomNumber(2),
            'category' => $this->faker->randomElement(['Electronics', 'Audio', 'Wearables', 'Tablets', 'Accessories', 'Storage']),
            'price' => $this->faker->randomFloat(2, 10, 2000),
            'stock' => $this->faker->numberBetween(1, 100),
        ];
    }
}
