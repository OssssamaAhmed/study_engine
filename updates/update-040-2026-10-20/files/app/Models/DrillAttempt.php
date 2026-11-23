<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrillAttempt extends Model
{
    protected $guarded = [];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
