<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StruggleNote extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved' => 'bool'];
    }

    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }
}
