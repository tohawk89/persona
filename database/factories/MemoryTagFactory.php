<?php

namespace Database\Factories;

use App\Models\MemoryTag;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemoryTagFactory extends Factory
{
    protected $model = MemoryTag::class;

    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'target' => $this->faker->randomElement(['user', 'self']),
            'category' => $this->faker->word(),
            'value' => $this->faker->sentence(),
            'context' => $this->faker->optional()->sentence(),
            'importance' => $this->faker->numberBetween(1, 5),
        ];
    }
}
