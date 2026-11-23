<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Services\MissionReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MissionReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MISSION_MD = <<<'MD'
        # Mission — 2026-07-16 — Test Run

        ## Briefing
        A briefing about gates and loops.

        ## Drill 1 — Gatekeeper [NEW]
        Drill one body about if/else.

        **Concepts:** if/else

        ## Drill 2 — Loop Patrol [REVIEW]
        Drill two body about for loops.

        **Concepts:** for loops

        ## Boss Fight — Payload Purge
        Boss body integrating everything.

        **Concepts:** everything

        ## Submission format
        Paste code.

        ## Solutions

        ### Solution — Drill 1 — Gatekeeper
        solution one body

        Concepts used: if/else

        ### Solution — Drill 2 — Loop Patrol
        solution two body

        Concepts used: for loops

        ### Solution — Boss Fight — Payload Purge
        boss solution body

        Concepts used: everything
        MD;

    private function makeMission(): Exercise
    {
        return Exercise::create([
            'date' => today()->toDateString(),
            'mission_md' => self::MISSION_MD,
            'status' => 'pending',
        ]);
    }

    public function test_drill_reference_returns_drill_and_its_solution(): void
    {
        $e = $this->makeMission();

        $out = app(MissionReference::class)->resolve("help me with M{$e->id} D2");

        $this->assertStringContainsString('Loop Patrol', $out);
        $this->assertStringContainsString('Drill two body', $out);
        $this->assertStringContainsString('solution two body', $out);
        $this->assertStringNotContainsString('Drill one body', $out);
    }

    public function test_mission_reference_returns_whole_mission(): void
    {
        $e = $this->makeMission();

        $out = app(MissionReference::class)->resolve("what was M{$e->id} about?");

        $this->assertStringContainsString('A briefing about gates and loops', $out);
        $this->assertStringContainsString('Boss body', $out);
    }

    public function test_boss_reference_returns_boss_and_solution(): void
    {
        $e = $this->makeMission();

        $out = app(MissionReference::class)->resolve("stuck on M{$e->id} B1");

        $this->assertStringContainsString('Payload Purge', $out);
        $this->assertStringContainsString('boss solution body', $out);
        $this->assertStringNotContainsString('Drill one body', $out);
    }

    public function test_bare_drill_reference_uses_newest_mission(): void
    {
        Exercise::create(['date' => today()->subDay()->toDateString(), 'mission_md' => '# older', 'status' => 'evaluated']);
        $this->makeMission();

        $out = app(MissionReference::class)->resolve('I do not get D1');

        $this->assertStringContainsString('Gatekeeper', $out);
    }

    public function test_unknown_and_missing_references_return_notes_not_errors(): void
    {
        $e = $this->makeMission();
        $svc = app(MissionReference::class);

        $this->assertStringContainsString('no mission with that number', $svc->resolve('M999'));
        $this->assertStringContainsString('has no drill 9', $svc->resolve("M{$e->id} D9"));
        $this->assertSame('', $svc->resolve('no references in this text'));
    }

    public function test_exercise_page_shows_the_mission_reference(): void
    {
        $e = $this->makeMission();

        $this->get("/exercise/{$e->id}")
            ->assertOk()
            ->assertSee("Mission M{$e->id}");
    }

    public function test_chat_attaches_referenced_material_to_the_llm_request(): void
    {
        $e = $this->makeMission();

        Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => 'Sure.']]]]));

        $this->post('/chat', ['message' => "Explain M{$e->id} D2 please"])->assertRedirect('/chat');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'REFERENCED MATERIAL')
            && str_contains($request->body(), 'Drill two body'));
    }
}
