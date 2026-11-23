<?php

namespace App\Models;

use App\Models\Concerns\ScopedToMode;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use ScopedToMode;

    protected $fillable = [
        'type',
        'title',
        'message',
        'link',
        'is_read',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }
}
