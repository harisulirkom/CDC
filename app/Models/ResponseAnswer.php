<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseAnswer extends Model
{
    protected $fillable = [
        'response_id',
        'question_id',
        'jawaban',
        'val_int',
        'val_decimal',
        'val_date',
        'val_string',
    ];

    protected $casts = [
        'val_int' => 'integer',
        'val_decimal' => 'decimal:2',
        'val_date' => 'date',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
