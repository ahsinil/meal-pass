<?php

namespace Database\Factories;

use App\Models\MealSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pickup>
 */
class PickupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $officer = User::where('is_active', 1)->where('is_admin', 1)->inRandomOrder()->pluck('id')->toArray();
        $pickedBy = User::where('is_active', 1)->where('is_admin', 0)->inRandomOrder()->pluck('id')->toArray();
        $mealSession = MealSession::inRandomOrder()->pluck('id')->toArray();
        $mealSessionId = fake()->randomElement($mealSession);
        $mealDate = MealSession::find($mealSessionId)->date;

        return [
            'officer_id' => fake()->randomElement($officer),
            'picked_by' => fake()->randomElement($pickedBy),
            'meal_session_id' => $mealSessionId,
            'picked_at' => $mealDate . fake()->date(' H:i:s'),
            'method' => fake()->randomElement(['manual', 'qr', 'camera']),
            'overriden' => 0,
            'overriden_reason' => '',
        ];
    }
}
