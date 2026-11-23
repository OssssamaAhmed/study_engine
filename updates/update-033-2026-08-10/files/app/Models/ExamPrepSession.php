<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class ExamPrepSession extends Model
{
    use ScopedToMode;

    protected $fillable = [
        'exam_target',
        'method',
        'study_guide_text',
        'session_data',
        'user_submission',
        'evaluation_md',
        'score',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'session_data' => 'array',
            'score' => 'integer',
        ];
    }
}
