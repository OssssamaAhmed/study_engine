<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never read the user's real flat files (learner name,
        // instructions) — concept selection now consults instructions.
        Storage::fake('local');
    }
}
