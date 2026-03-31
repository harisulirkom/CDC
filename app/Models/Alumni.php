<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumni extends Model
{
    protected $fillable = [
        'nama',
        'nim',
        'nik',
        'prodi',
        'fakultas',
        'tahun_masuk',
        'tahun_lulus',
        'email',
        'no_hp',
        'alamat',
        'tanggal_lahir',
        'foto',
        'sent',
        'status_pekerjaan',
    ];

    protected $casts = [
        'sent' => 'boolean',
        'tanggal_lahir' => 'date',
        'tahun_masuk' => 'integer',
        'tahun_lulus' => 'integer',
    ];
}
