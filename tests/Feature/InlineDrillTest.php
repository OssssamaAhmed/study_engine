<?php

namespace Tests\Feature;

use App\Models\DrillAttempt;
use App\Models\Exercise;
use App\Services\MissionReference;
use App\Support\CodeComments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InlineDrillTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MISSION = <<<'MD'
        # Mission — 2026-10-20 — Dict Loops

        ## Briefing
        Loop over things.

        ## Drill 1 — Flat Dict [NEW]
        **Setup:**
        ```python
        ## not a heading, just a comment
        cfg = {"a": 1}
        ```
        **Task:** print each key.

        ## Drill 2 — List of Dicts [REVIEW]
        **Task:** print each name.

        ## Boss Fight — Agent Audit
        Build the audit.

        ## Solutions
        ### Solution — Flat Dict
        for k in cfg: print(k)
        MD;

    private function mission(): Exercise
    {
        return Exercise::create(['date' => today()->toDateString(), 'mission_md' => self::MISSION, 'status' => 'pending']);
    }

    private function fakeLlm(array $reply): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]]])]);
    }

    public function test_sections_tag_drills_and_bosses_and_ignore_headings_inside_code(): void
    {
        $keys = array_column(app(MissionReference::class)->sections(self::MISSION), 'key');

        $this->assertSame([null, null, 'D1', 'D2', 'B1', null], $keys);
    }

    public function test_copy_text_drops_heading_but_keeps_description(): void
    {
        $copy = app(MissionReference::class)->copyText(<<<'MD'
            ## Drill 1 — Flat Dict [LEVEL 1: FOUNDATION]
            You are reading a config file.

            **Setup:**
            ```python
            cfg = {"a": 1}
            ```

            **Task:**
            - loop over `cfg`

            **Output:** `a`

            **Concepts:** dict loops

            ---
            MD);

        $this->assertStringStartsWith('You are reading a config file.', $copy);
        $this->assertStringContainsString('**Setup:**', $copy);
        $this->assertStringNotContainsString('Drill 1', $copy);
        $this->assertStringNotContainsString('LEVEL 1', $copy);
        $this->assertStringEndsWith('**Concepts:** dict loops', $copy);
    }

    public function test_page_shows_copy_and_inline_box_under_drills_only(): void
    {
        $exercise = $this->mission();

        $this->get("/exercise/{$exercise->id}")
            ->assertOk()
            ->assertSee('Copy drill')
            ->assertSee('Copy Boss Fight')
            ->assertSee("/exercise/{$exercise->id}/drill/D1")
            ->assertSee("/exercise/{$exercise->id}/drill/D2")
            ->assertDontSee("/exercise/{$exercise->id}/drill/B1")
            ->assertSeeText('0 of 2 drills checked');
    }

    public function test_checking_a_drill_stores_score_and_sends_drill_with_solution(): void
    {
        $exercise = $this->mission();
        $reply = fn ($json) => ['choices' => [['message' => ['content' => json_encode($json)]]]];
        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push($reply(['score' => 85, 'feedback_md' => 'Correct — **nice**.']))
            ->push($reply(['score' => 40, 'feedback_md' => 'Missed a key.']))]);

        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => 'for k in cfg: print(k)'])
            ->assertOk()
            ->assertJson(['key' => 'D1', 'score' => 85])
            ->assertJsonPath('feedback_html', "<p>Correct — <strong>nice</strong>.</p>\n");

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Solution — Flat Dict'));
        // The checker is told to answer the learner's code comments, not skip them.
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'Your note:'));

        // Resubmitting replaces the drill's answer instead of piling up rows.
        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => 'print(cfg)'])->assertOk();

        $this->assertSame(1, DrillAttempt::count());
        $this->assertSame(40, DrillAttempt::first()->score);
        $this->assertSame('pending', $exercise->fresh()->status); // ladder untouched

        $this->get("/exercise/{$exercise->id}")->assertSeeText('1 of 2 drills checked');
    }

    public function test_code_comments_are_extracted_but_not_hashes_inside_strings(): void
    {
        $notes = CodeComments::extract("tag = \"#admin\"  # why a string?\nprint(tag)\n\n# I could use and here\nurl = 'http://x' // php note");

        $this->assertSame(['line 1: why a string?', 'line 4: I could use and here', 'line 5: php note'], $notes);
    }

    public function test_learner_comment_is_sent_as_notes_and_its_reply_shown(): void
    {
        $exercise = $this->mission();
        $this->fakeLlm(['score' => 100, 'feedback_md' => 'Correct.', 'note_reply_md' => 'It is in bullet 3 of the Task.']);

        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => "for k in cfg: print(k)\n# you never told me to print k"])
            ->assertOk();

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), "Learner's notes")
            && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'line 2: you never told me to print k'));
        $this->assertSame("Correct.\n\n**Your note:** It is in bullet 3 of the Task.", DrillAttempt::first()->feedback_md);
    }

    public function test_empty_note_reply_is_retried_once_then_falls_back_to_a_tutor_hint(): void
    {
        $exercise = $this->mission();
        $reply = fn ($json) => ['choices' => [['message' => ['content' => json_encode($json)]]]];
        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push($reply(['score' => 100, 'feedback_md' => 'Correct.']))
            ->push($reply(['score' => 100, 'feedback_md' => 'Correct.', 'note_reply_md' => 'Yes, `and` changes it.']))
            ->push($reply(['score' => 90, 'feedback_md' => 'Fine.']))
            ->push($reply(['score' => 90, 'feedback_md' => 'Fine.']))]);

        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => "print(cfg)\n# could I use and?"])->assertOk();
        $this->assertStringEndsWith('**Your note:** Yes, `and` changes it.', DrillAttempt::first()->feedback_md);

        // Second check: both tries skip the note — the learner is pointed to the tutor instead of silence.
        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => "print(cfg)\n# still unsure"])->assertOk();
        $this->assertStringContainsString("didn't answer your note", DrillAttempt::first()->feedback_md);
        Http::assertSentCount(4);
    }

    public function test_answer_without_comments_makes_one_call_and_no_note_line(): void
    {
        $exercise = $this->mission();
        $this->fakeLlm(['score' => 100, 'feedback_md' => 'Correct.']);

        $this->postJson("/exercise/{$exercise->id}/drill/D1", ['answer' => 'for k in cfg: print(k)'])->assertOk();

        Http::assertSentCount(1);
        $this->assertSame('Correct.', DrillAttempt::first()->feedback_md);
    }

    public function test_unknown_drill_is_rejected(): void
    {
        $exercise = $this->mission();

        $this->postJson("/exercise/{$exercise->id}/drill/D9", ['answer' => 'x'])->assertStatus(422);
        $this->postJson("/exercise/{$exercise->id}/drill/B1", ['answer' => 'x'])->assertNotFound();
    }

    public function test_full_evaluation_includes_inline_drills_and_allows_empty_paste(): void
    {
        $exercise = $this->mission();

        $this->post("/exercise/{$exercise->id}/submit", ['pasted_result' => ''])
            ->assertSessionHasErrors('pasted_result');

        DrillAttempt::create(['exercise_id' => $exercise->id, 'drill_key' => 'D2', 'answer' => 'print(agent["name"])', 'score' => 90]);
        $this->fakeLlm(['feedback_md' => 'ok', 'concept_scores' => [], 'suggested_struggle_notes' => []]);

        $this->post("/exercise/{$exercise->id}/submit", ['pasted_result' => ''])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->body(), 'solved inline')
            && str_contains($r->body(), 'print(agent[\\"name\\"])'));
        $this->assertSame('evaluated', $exercise->fresh()->status);
    }
}
