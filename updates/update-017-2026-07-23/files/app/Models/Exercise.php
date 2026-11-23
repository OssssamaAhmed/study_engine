<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function isSideQuest(): bool
    {
        return $this->type === 'side_quest';
    }

    public function scopeSideQuests($query)
    {
        return $query->where('type', 'side_quest');
    }

    /** Evocative title from the mission's first line ("# Mission — {date} — {title}"). */
    public function title(): string
    {
        return (string) str($this->mission_md)->before("\n")->afterLast('—')->trim() ?: 'Mission';
    }

    public function concepts()
    {
        return $this->belongsToMany(Concept::class, 'exercise_concept')->withPivot('role');
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }
}
