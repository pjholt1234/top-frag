<?php

namespace Database\Seeders;

use App\Models\DemoProcessingJob;
use App\Models\GameMatch;
use App\Enums\ProcessingStatus;
use Illuminate\Database\Seeder;

class DemoProcessingJobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample processing jobs
        DemoProcessingJob::factory()
            ->count(5)
            ->pending()
            ->create();

        DemoProcessingJob::factory()
            ->count(3)
            ->processing()
            ->create();

        DemoProcessingJob::factory()
            ->count(10)
            ->completed()
            ->create();

        DemoProcessingJob::factory()
            ->count(2)
            ->failed()
            ->create();

        // Create some jobs associated with existing matches
        $matches = GameMatch::take(3)->get();
        foreach ($matches as $match) {
            DemoProcessingJob::factory()
                ->forMatch($match)
                ->completed()
                ->create();
        }
    }
}
