<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class PhaseProgress extends Model
{
    use ScopedToMode;

    protected $table = 'phase_progress';

    /** The project passed (70+), so the phase is finished. */
    public const COMPLETED = 'completed';

    /**
     * The learner got ONE graded attempt and moved on to the next phase (user requirement: the next phase's study must
     * not wait for a perfect project). The project stays open for more attempts; passing it later makes it COMPLETED.
     */
    public const MOVED_ON = 'moved_on';

    /** Phase numbers the learner has moved past — finished OR moved on with the project still being improved. */
    public static function pastNumbers(): array
    {
        return static::whereIn('status', [self::COMPLETED, self::MOVED_ON])->pluck('phase_number')->all();
    }

    protected $fillable = [
        'phase_number',
        'status',
        'mega_project_md',
        'research_context',
        'implementation_plan_md',
        'skeleton_md',
        'project_submission',
        'project_eval_md',
        'project_score',
        'project_attempts',
        'fix_plans',
        'completed_at',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'project_score' => 'integer',
            'project_attempts' => 'array',
            'fix_plans' => 'array',
            'phase_number' => 'integer',
        ];
    }
}
