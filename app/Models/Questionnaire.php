<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Questionnaire extends Model
{
    protected $fillable = [
        'judul',
        'deskripsi',
        'status',
        'audience',
        'chip_text',
        'estimated_time',
        'is_active',
        'extra_questions',
        'tanggal_mulai',
        'tanggal_akhir',
    ];

    protected $casts = [
        'tanggal_mulai' => 'datetime',
        'tanggal_akhir' => 'datetime',
        'is_active' => 'boolean',
        'extra_questions' => 'array',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('urutan');
    }

    public function getAudienceNormalizedAttribute(): string
    {
        $raw = strtolower(trim($this->audience ?? ''));
        if (str_contains($raw, 'pengguna')) {
            return 'pengguna';
        }
        if (str_contains($raw, 'umum')) {
            return 'umum';
        }
        return 'alumni';
    }
}
