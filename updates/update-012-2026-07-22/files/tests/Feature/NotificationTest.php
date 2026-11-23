<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Notification;
use App\Models\PhaseProgress;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    public function test_notifications_page_loads_successfully()
    {
        $response = $this->get('/notifications');

        $response->assertStatus(200);
        $response->assertSee('Notifications Center');
    }

    public function test_checkpoint_alert_does_not_trigger_prematurely_in_phase_1()
    {
        // Add concepts only for Phase 1
        $topic1 = Topic::where('phase_number', 1)->first();
        Concept::create([
            'topic_id' => $topic1->id,
            'name' => 'For loops in Python',
            'summary' => 'Python for loop iteration',
            'first_seen_at' => now(),
            'next_review_at' => now(),
        ]);

        $this->get('/notifications');

        // Checkpoint 1 requires reaching Phase 5, so no premature Checkpoint 1 alert
        $this->assertDatabaseMissing('notifications', [
            'type' => 'checkpoint_near',
        ]);
    }

    public function test_checkpoint_alert_triggers_when_learner_reaches_phase_5()
    {
        // Add concept for Phase 5 (final phase of Checkpoint 1 range)
        $topic5 = Topic::where('phase_number', 5)->first();
        Concept::create([
            'topic_id' => $topic5->id,
            'name' => 'Machine Learning Classification',
            'summary' => 'ML model evaluation and classification',
            'first_seen_at' => now(),
            'next_review_at' => now(),
        ]);

        $this->get('/notifications');

        // Now Checkpoint 1 alert triggers because learner reached Phase 5
        $this->assertDatabaseHas('notifications', [
            'type' => 'checkpoint_near',
        ]);
    }

    public function test_can_mark_notification_as_read()
    {
        $note = Notification::create([
            'type' => 'checkpoint_near',
            'title' => 'Test Alert',
            'message' => 'Test message',
            'is_read' => false,
        ]);

        $response = $this->post("/notifications/{$note->id}/read");

        $response->assertRedirect('/notifications');
        $this->assertDatabaseHas('notifications', [
            'id' => $note->id,
            'is_read' => true,
        ]);
    }

    public function test_can_delete_single_notification()
    {
        $note = Notification::create([
            'type' => 'checkpoint_near',
            'title' => 'Delete Test Alert',
            'message' => 'Test message to delete',
            'is_read' => false,
        ]);

        $response = $this->post("/notifications/{$note->id}/delete");

        $response->assertRedirect('/notifications');
        $this->assertDatabaseMissing('notifications', [
            'id' => $note->id,
        ]);
    }

    public function test_can_clear_all_notifications()
    {
        Notification::create(['type' => 'test', 'title' => 'T1', 'message' => 'M1']);
        Notification::create(['type' => 'test', 'title' => 'T2', 'message' => 'M2']);

        $response = $this->post('/notifications/clear-all');

        $response->assertRedirect('/notifications');
        $this->assertDatabaseCount('notifications', 0);
    }
}
