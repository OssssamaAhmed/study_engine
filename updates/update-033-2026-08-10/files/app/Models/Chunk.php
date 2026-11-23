<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class Chunk extends Model
{
    use ScopedToMode;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }
}
