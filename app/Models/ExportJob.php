<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    protected $fillable = [
        'questionnaire_id',
        'status',
        'format',
        'filters',
        'file_path',
        'error_message',
        'requested_by',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }
}
