<?php

namespace Tests\Feature;

use App\Models\Notification;
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

    public function test_notifications_page_loads_and_generates_alerts()
    {
        $response = $this->get('/notifications');

        $response->assertStatus(200);
        $response->assertSee('Notifications Center');

        $this->assertDatabaseHas('notifications', [
            'is_read' => false,
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
