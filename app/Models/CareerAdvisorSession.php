<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerAdvisorSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'persona_id',
        'profile_data',
        'form_completion_percent',
        'confidence_band',
        'ready_for_generate',
        'generation_status',
        'analysis_id',
        'recommendation_data',
        'recommendation_source',
        'generated_at',
        'next_action',
        'action_saved_at',
        'relevance_score',
        'feedback_note',
        'feedback_saved_at',
    ];

    protected $casts = [
        'profile_data' => 'array',
        'recommendation_data' => 'array',
        'ready_for_generate' => 'boolean',
        'generated_at' => 'datetime',
        'action_saved_at' => 'datetime',
        'feedback_saved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'session_id';
    }
}
