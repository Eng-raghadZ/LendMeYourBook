<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalCopies = fake()->numberBetween(1, 10);

        return [
            'owner_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'description' => fake()->optional()->paragraph(),
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies,
            'rental_price' => number_format(fake()->numberBetween(0, 999999) / 100, 2, '.', ''),
            'refundable_deposit' => number_format(fake()->numberBetween(1, 999999) / 100, 2, '.', ''),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
