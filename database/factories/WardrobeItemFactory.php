<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WardrobeItem>
 */
class WardrobeItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slotName = $this->faker->randomElement([
            'casual_daytime',
            'casual_nighttime',
            'formal',
            'workout',
            'sleepwear',
        ]);

        $outfits = [
            'casual_daytime' => [
                ['desc' => 'Blue jeans with white floral sundress and sandals', 'upper' => 'white floral sundress', 'lower' => null, 'foot' => 'sandals'],
                ['desc' => 'Black leggings with oversized gray sweater and sneakers', 'upper' => 'oversized gray sweater', 'lower' => 'black leggings', 'foot' => 'sneakers'],
                ['desc' => 'Denim shorts with striped t-shirt and flip-flops', 'upper' => 'striped t-shirt', 'lower' => 'denim shorts', 'foot' => 'flip-flops'],
            ],
            'casual_nighttime' => [
                ['desc' => 'Soft pink pajama set with fuzzy slippers', 'upper' => 'pink pajama top', 'lower' => 'pink pajama pants', 'foot' => 'fuzzy slippers'],
                ['desc' => 'Oversized t-shirt with comfortable shorts', 'upper' => 'oversized t-shirt', 'lower' => 'comfortable shorts', 'foot' => null],
            ],
            'formal' => [
                ['desc' => 'Black cocktail dress with heels', 'upper' => 'black cocktail dress', 'lower' => null, 'foot' => 'heels'],
                ['desc' => 'Navy blazer with white blouse and pencil skirt', 'upper' => 'navy blazer with white blouse', 'lower' => 'pencil skirt', 'foot' => 'heels'],
            ],
            'workout' => [
                ['desc' => 'Black sports bra with matching leggings and running shoes', 'upper' => 'black sports bra', 'lower' => 'black leggings', 'foot' => 'running shoes'],
                ['desc' => 'Gray tank top with yoga pants and trainers', 'upper' => 'gray tank top', 'lower' => 'yoga pants', 'foot' => 'trainers'],
            ],
            'sleepwear' => [
                ['desc' => 'Silk nightgown in cream color', 'upper' => 'cream silk nightgown', 'lower' => null, 'foot' => null],
                ['desc' => 'Cotton pajama set with cute prints', 'upper' => 'cotton pajama top', 'lower' => 'cotton pajama pants', 'foot' => null],
            ],
        ];

        $outfit = $this->faker->randomElement($outfits[$slotName]);

        return [
            'persona_id' => Persona::factory(),
            'slot_name' => $slotName,
            'description' => $outfit['desc'],
            'upper_body' => $outfit['upper'],
            'lower_body' => $outfit['lower'],
            'footwear' => $outfit['foot'],
            'accessories' => $this->faker->optional(0.3)->randomElement(['sunglasses', 'watch', 'small handbag', 'backpack']),
            'is_primary' => false,
            'last_worn_at' => $this->faker->optional(0.5)->dateTimeBetween('-30 days', 'now'),
            'wear_count' => $this->faker->numberBetween(0, 20),
        ];
    }

    /**
     * Indicate that this is a primary outfit.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
