<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'product_code' => fake()->unique()->bothify('PRD-####'),
            'product_name' => fake()->words(3, true),
            'normal_price' => 'Rp '.fake()->numberBetween(5, 100).'.000',
            'wholesale_price' => 'Rp '.fake()->numberBetween(3, 80).'.000',
            'video_path' => 'videos/'.fake()->uuid().'.mp4',
            'cover_path' => null,
            'video_size_bytes' => fake()->numberBetween(1024, 1048576),
        ];
    }
}
