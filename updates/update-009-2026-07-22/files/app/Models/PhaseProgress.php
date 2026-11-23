<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseProgress extends Model
{
    protected $table = 'phase_progress';

    protected $fillable = [
        'phase_number',
        'status',
        'mega_project_md',
        'project_submission',
        'project_eval_md',
        'project_score',
        'completed_at',
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
