<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Support\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityStreakTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_any_page_visit_records_today_as_an_active_day(): void
    {
        $this->get('/concepts')->assertOk();

        $this->assertSame(1, ActivityLog::streak());
        $this->assertSame(today()->toDateString(), trim(Storage::disk('local')->get('activity-days.txt')));
    }

    public function test_consecutive_days_count_back_from_today(): void
    {
        Storage::disk('local')->put('activity-days.txt', implode("\n", [
            today()->subDays(2)->toDateString(),
            today()->subDay()->toDateString(),
        ]));

        $this->get('/')->assertOk();

        $this->assertSame(3, ActivityLog::streak());
    }

    public function test_a_missed_day_resets_the_streak(): void
    {
        Storage::disk('local')->put('activity-days.txt', today()->subDays(3)->toDateString());

        $this->get('/')->assertOk();

        $this->assertSame(1, ActivityLog::streak());
    }

    public function test_an_unvisited_today_does_not_break_yesterdays_streak(): void
    {
        Storage::disk('local')->put('activity-days.txt', implode("\n", [
            today()->subDays(2)->toDateString(),
            today()->subDay()->toDateString(),
        ]));

        $this->assertSame(2, ActivityLog::streak());
    }

    public function test_first_run_backfills_from_existing_study_data(): void
    {
        Exercise::create([
            'date' => today()->subDay()->toDateString(),
            'mission_md' => '# Mission — X',
            'status' => 'evaluated',
        ]);

        $this->get('/')->assertOk();

        // Yesterday earned by the stored mission, today by the visit itself.
        $this->assertSame(2, ActivityLog::streak());
    }

    public function test_clear_erases_the_streak(): void
    {
        ActivityLog::touch();
        ActivityLog::clear();

        $this->assertSame(0, ActivityLog::streak());
    }
}
