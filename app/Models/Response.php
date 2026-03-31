<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Response extends Model
{
    protected $fillable = [
        'alumni_id',
        'questionnaire_id',
        'attempt_ke',
        'form_data',
    ];

    protected $casts = [
        'form_data' => 'array',
    ];

    public function alumni(): BelongsTo
    {
        return $this->belongsTo(Alumni::class);
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ResponseAnswer::class);
    }
}
