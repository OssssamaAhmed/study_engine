<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class PhaseProgress extends Model
{
    use ScopedToMode;

    protected $table = 'phase_progress';

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
        'completed_at',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'project_score' => 'integer',
            'phase_number' => 'integer',
        ];
    }
}
