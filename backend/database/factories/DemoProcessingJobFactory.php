<?php

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DemoProcessingJob>
 */
class DemoProcessingJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DemoProcessingJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->unique()->uuid(),
            'match_id' => null, // Will be set when needed
            'processing_status' => $this->faker->randomElement(ProcessingStatus::cases()),
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'error_message' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the job is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::PENDING,
            'progress_percentage' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the job is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::PROCESSING,
            'progress_percentage' => $this->faker->numberBetween(1, 99),
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the job is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::COMPLETED,
            'progress_percentage' => 100,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the job failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProcessingStatus::FAILED,
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    /**
     * Associate the job with a specific match.
     */
    public function forMatch(GameMatch $match): static
    {
        return $this->state(fn (array $attributes) => [
            'match_id' => $match->id,
        ]);
    }
}
