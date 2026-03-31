<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankItem extends Model
{
    protected $fillable = [
        'pertanyaan',
        'tipe',
        'pilihan',
        'is_required',
        'metadata',
    ];

    protected $casts = [
        'pilihan' => 'array',
        'metadata' => 'array',
        'is_required' => 'boolean',
    ];
}
