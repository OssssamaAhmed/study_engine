<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class Transcript extends Model
{
    use ScopedToMode;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function chunks()
    {
        return $this->hasMany(Chunk::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
