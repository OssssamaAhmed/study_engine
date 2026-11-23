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
}
