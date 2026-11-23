<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Faked here, not in setUp(): RefreshDatabase's migrate+seed runs before
     * setUp()'s body does, and seeding now reads StudyMode (via
     * RoadmapService), which reads the local disk. Faking after seeding
     * would let a real, locally toggled active-mode.txt leak into every
     * test run on this machine. Tests must also never read the user's real
     * flat files (learner name, instructions) — concept selection consults
     * instructions.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        Storage::fake('local');
    }
}
