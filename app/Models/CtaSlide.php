<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtaSlide extends Model
{
    protected $fillable = [
        'tag',
        'title',
        'highlight',
        'subtitle',
        'chips',
        'primary',
        'secondary',
        'stats',
        'order',
    ];

    protected $casts = [
        'chips' => 'array',
        'primary' => 'array',
        'secondary' => 'array',
        'stats' => 'array',
    ];
}
