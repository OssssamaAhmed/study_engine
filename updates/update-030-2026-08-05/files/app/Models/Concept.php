<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concept extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'next_review_at' => 'date',
            'retired' => 'bool',
        ];
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function struggleNotes()
    {
        return $this->hasMany(StruggleNote::class);
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'exercise_concept')->withPivot('role');
    }
}
