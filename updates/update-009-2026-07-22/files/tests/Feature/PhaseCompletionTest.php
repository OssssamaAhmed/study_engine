<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\PhaseProgress;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed topics from roadmap JSON
        $this->artisan('db:seed');
    }

    public function test_phases_index_page_loads_successfully()
    {
        $response = $this->get('/phases');

        $response->assertStatus(200);
        $response->assertSee('Phases &amp; Mega Portfolio Projects', false);
    }

    public function test_phase_show_page_loads_successfully()
    {
        $response = $this->get('/phases/1');

        $response->assertStatus(200);
        $response->assertSee('Phase 1: Git, GitHub &amp; Python Programming', false);
    }

    public function test_phase_completion_is_declined_when_concepts_and_project_are_missing()
    {
        $response = $this->post('/phases/1/complete');

        $response->assertRedirect('/phases/1');
        $response->assertSessionHas('error');

        $error = session('error');
        $this->assertStringContainsString('First learn and practice these topics', $error);
    }

    public function test_phase_completion_succeeds_when_concepts_and_project_are_passed()
    {
        $topic = Topic::where('phase_number', 1)->first();

        // Add all required concepts for Phase 1
        $required = ['for loops', 'functions', 'error handling', 'virtual environments', 'git basics', 'pip'];
        foreach ($required as $name) {
            Concept::create([
                'topic_id' => $topic->id,
                'name' => $name,
                'summary' => "Summary of {$name}",
                'first_seen_at' => now(),
                'next_review_at' => now()->addDay(),
            ]);
        }

        // Set project as passed
        PhaseProgress::create([
            'phase_number' => 1,
            'status' => 'pending_project',
            'project_score' => 85,
        ]);

        $response = $this->post('/phases/1/complete');

        $response->assertRedirect('/phases/1');
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('phase_progress', [
            'phase_number' => 1,
            'status' => 'completed',
        ]);
    }
}
