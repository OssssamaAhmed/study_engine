<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPrepSession extends Model
{
    protected $fillable = [
        'exam_target',
        'method',
        'study_guide_text',
        'session_data',
        'user_submission',
        'evaluation_md',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'session_data' => 'array',
            'score' => 'integer',
        ];
    }
}
