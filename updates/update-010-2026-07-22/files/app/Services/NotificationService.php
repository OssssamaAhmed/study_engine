<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function __construct(
        protected RoadmapService $roadmap
    ) {}

    /** Check ingested study content & concept practice to generate automated alerts. */
    public function checkAndGenerate(): void
    {
        $upcomingExams = $this->roadmap->getUpcomingExams();

        foreach ($upcomingExams as $exam) {
            $type = $exam['type'] === 'checkpoint' ? 'checkpoint_near' : 'cert_near';
            $title = $exam['title'];

            $exists = Notification::where('type', $type)
                ->where('title', $title)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if (! $exists) {
                if ($exam['type'] === 'checkpoint') {
                    $msg = "Based on your ingested content & practice ({$exam['phase_range']}), {$title} is near! Go to the Exam Preparation tab to start preparing.";
                } else {
                    $msg = "Certification Target: {$title} is approaching based on your current study content ({$exam['when']}). Start preparing in the Exam Preparation tab!";
                }

                Notification::create([
                    'type' => $type,
                    'title' => $title,
                    'message' => $msg,
                    'link' => '/exam-prep',
                    'is_read' => false,
                ]);
            }
        }
    }

    /** Retrieve unread notifications. */
    public function getUnread(): Collection
    {
        $this->checkAndGenerate();

        return Notification::where('is_read', false)->orderByDesc('created_at')->get();
    }

    /** Retrieve all notifications paginated or grouped. */
    public function getAll()
    {
        $this->checkAndGenerate();

        return Notification::orderByDesc('created_at')->paginate(15);
    }
}
