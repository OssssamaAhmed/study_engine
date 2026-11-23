<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Concept;
use App\Models\Exercise;
use App\Models\StruggleNote;
use App\Models\Topic;
use App\Services\RoadmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgressProfileResetTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_dashboard_greets_saved_name_and_shows_roadmap_progress(): void
    {
        $this->post('/settings/profile', ['name' => 'Osama'])->assertRedirect('/settings');

        $week = app(RoadmapService::class)->currentWeek();
        $total = (int) Topic::max('week_end');

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome back, Osama.')
            ->assertSee("week {$week} of {$total}")
            ->assertSee('of the roadmap covered');
    }

    public function test_reset_requires_exact_confirmation_and_changes_nothing_otherwise(): void
    {
        $concept = Concept::create([
            'topic_id' => Topic::first()->id,
            'name' => 'for loops',
            'summary' => 'repeat over a sequence',
        ]);

        $this->post('/settings/reset', ['confirm' => 'reset'])->assertSessionHasErrors('confirm');
        $this->post('/settings/reset', [])->assertSessionHasErrors('confirm');

        $this->assertNotNull($concept->fresh());
    }

    public function test_reset_wipes_all_progress_but_keeps_roadmap_topics(): void
    {
        $transcriptId = DB::table('transcripts')->insertGetId(['raw_text' => 'loops repeat things']);
        $concept = Concept::create(['topic_id' => Topic::first()->id, 'name' => 'loops', 'summary' => 's']);
        DB::table('chunks')->insert(['transcript_id' => $transcriptId, 'text' => 'chunk', 'embedding' => json_encode([0.1])]);
        $exercise = Exercise::create(['date' => today()->toDateString(), 'mission_md' => '# M', 'status' => 'pending']);
        $exercise->concepts()->attach($concept->id, ['role' => 'new']);
        Attempt::create(['exercise_id' => $exercise->id, 'pasted_result' => 'x', 'evaluation_md' => 'y', 'score' => 50]);
        StruggleNote::create(['concept_id' => $concept->id, 'note' => 'hard', 'weight' => 1]);

        // Intercept the roadmap start_date write so the real JSON on disk stays untouched.
        File::partialMock()->shouldReceive('put')->once()->andReturn(true);

        $this->post('/settings/reset', ['confirm' => 'RESET'])->assertRedirect('/settings');

        foreach (['transcripts', 'chunks', 'concepts', 'exercises', 'exercise_concept', 'attempts', 'struggle_notes'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty");
        }
        $this->assertSame(21, Topic::count());
    }
}
