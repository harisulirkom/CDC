<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $fillable = [
        'title',
        'company',
        'company_profile',
        'location',
        'work_mode',
        'job_type',
        'category',
        'deadline',
        'status',
        'published_at',
        'summary',
        'responsibilities',
        'qualifications',
        'compensation',
        'benefits',
        'apply',
    ];

    protected $casts = [
        'deadline' => 'date',
        'published_at' => 'datetime',
        'responsibilities' => 'array',
        'qualifications' => 'array',
        'benefits' => 'array',
    ];
}
