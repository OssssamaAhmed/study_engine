<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index()
    {
        $notifications = $this->notificationService->getAll();

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(int $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);

        if ($notification->link) {
            return redirect($notification->link);
        }

        return redirect('/notifications')->with('status', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        Notification::where('is_read', false)->update(['is_read' => true]);

        return redirect('/notifications')->with('status', 'All notifications marked as read.');
    }

    public function destroy(int $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return redirect('/notifications')->with('status', 'Notification deleted.');
    }

    public function clearAll()
    {
        Notification::query()->delete();

        return redirect('/notifications')->with('status', 'All notifications cleared.');
    }
}
