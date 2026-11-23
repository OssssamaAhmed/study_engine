<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
