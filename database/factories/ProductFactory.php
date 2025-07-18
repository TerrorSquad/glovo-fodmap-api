<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->uuid(),
            'name'        => fake()->randomElement([
                'Banana',
                'Apple',
                'Orange',
                'Chicken Breast',
                'Rice',
                'Onion',
                'Garlic',
                'Wheat Bread',
                'Milk',
                'Cheese',
            ]),
            'category' => fake()->randomElement([
                'Fruits',
                'Vegetables',
                'Meat',
                'Dairy',
                'Grains',
                'Beverages',
            ]),
            'is_food'      => fake()->optional(0.9)->boolean(), // 90% chance of being classified, 10% null
            'status'       => fake()->randomElement(['LOW', 'MODERATE', 'HIGH']),
            'explanation'  => fake()->optional(0.8)->sentence(),
            'processed_at' => fake()->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    /**
     * Indicate that the product is unprocessed.
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);
    }

    /**
     * Indicate that the product has low FODMAP status.
     */
    public function lowFodmap(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the product has high FODMAP status.
     */
    public function highFodmap(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'       => 'HIGH',
            'processed_at' => now(),
        ]);
    }
}
