<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use ScopedToMode;

    public $timestamps = false;

    protected $guarded = [];
}
