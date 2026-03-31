<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'questionnaire_id',
        'question_bank_item_id',
        'pertanyaan',
        'tipe',
        'status_condition',
        'pilihan',
        'is_required',
        'urutan',
    ];

    protected $casts = [
        'pilihan' => 'array',
        'is_required' => 'boolean',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function questionBankItem(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class);
    }
}
