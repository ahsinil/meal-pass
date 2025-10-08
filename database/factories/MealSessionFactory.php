<?php

namespace Database\Factories;

use App\Models\MealWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealSession>
 */
class MealSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $windows = MealWindow::inRandomOrder()->pluck('id')->toArray();

        return [
            'date' => '2025-' . fake()->date('m-d'),
            'meal_window_id' => fake()->randomElement($windows),
            'qty' => fake()->numberBetween(180, 299),
            'notes' => fake()->sentence(),
            'is_active' => 0,
        ];
    }
}
