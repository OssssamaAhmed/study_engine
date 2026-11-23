<?php

namespace Tests\Feature;

use App\Models\ExamPrepSession;
use App\Services\LlmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamPrepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_exam_prep_hub_page_loads_successfully()
    {
        $response = $this->get('/exam-prep');

        $response->assertStatus(200);
        $response->assertSee('Exam Preparation Hub');
    }

    public function test_can_ingest_external_study_guide()
    {
        $response = $this->post('/exam-prep/ingest', [
            'target' => 'Machine Learning Exam',
            'report_text' => 'Comprehensive research report on ML algorithms, distractor questions, and model evaluations.',
        ]);

        $response->assertRedirect('/exam-prep');
        $this->assertDatabaseHas('exam_prep_sessions', [
            'exam_target' => 'Machine Learning Exam',
            'method' => 'ingested_guide',
        ]);
    }

    public function test_can_start_exam_prep_session()
    {
        // Mock LLM chatJson response for generating exam session
        $this->mock(LlmService::class, function ($mock) {
            $mock->shouldReceive('chatJson')->andReturn([
                'exam_target' => 'Checkpoint 1',
                'method' => 'active_recall',
                'questions' => [
                    [
                        'id' => 1,
                        'question' => 'What is a Python for loop?',
                        'options' => ['A' => 'Loop', 'B' => 'Func'],
                        'correct_answer' => 'A',
                        'explanation' => 'Iterates over sequence.',
                    ],
                ],
            ]);
        });

        $response = $this->post('/exam-prep/start', [
            'target' => 'Checkpoint 1',
            'method' => 'active_recall',
        ]);

        $session = ExamPrepSession::latest()->first();
        $this->assertNotNull($session);
        $response->assertRedirect("/exam-prep/session/{$session->id}");
    }
}
