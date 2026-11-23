<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestPageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_ingest_form_lists_roadmap_topics(): void
    {
        $this->get('/ingest')
            ->assertOk()
            ->assertSee('Paste a transcript')
            ->assertSee('Practical DSA');
    }
}
