<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->firstName(),
            'about_description' => $this->faker->sentence(),
            'system_prompt' => $this->faker->paragraph(),
            'appearance_description' => $this->faker->sentence(),
            'physical_traits' => $this->faker->sentence(),
            'gender' => $this->faker->randomElement(['female', 'male', 'non-binary']),
            'wake_time' => '08:00',
            'sleep_time' => '23:00',
            'voice_frequency' => $this->faker->randomElement(['never', 'rare', 'moderate', 'frequent']),
            'image_frequency' => $this->faker->randomElement(['never', 'rare', 'moderate', 'frequent']),
            'is_active' => true,
            'telegram_bot_token' => null,
            'telegram_bot_username' => null,
        ];
    }
}
