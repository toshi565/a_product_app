<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Artist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'title' => fake()->jobTitle(),
            'genre' => fake()->randomElement(Artist::allowedGenres()),
            'bio' => fake()->paragraph(),
            'display_order' => fake()->numberBetween(1, 3),
            'is_visible' => fake()->boolean(80), // 80%の確率で公開
            'portrait_path' => fake()->optional()->imageUrl(300, 300, 'people'),
        ];
    }

    /**
     * Indicate that the artist is visible.
     */
    public function visible(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_visible' => true,
        ]);
    }

    /**
     * Indicate that the artist is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
