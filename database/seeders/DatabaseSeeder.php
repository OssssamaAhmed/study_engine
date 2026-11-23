<?php

namespace Database\Seeders;

use App\Services\RoadmapService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $count = app(RoadmapService::class)->syncTopics();

        $this->command?->info("Synced {$count} topics from the roadmap JSON.");
    }
}
