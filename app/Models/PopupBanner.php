<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopupBanner extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image_path',
        'image_url',
        'link_url',
        'button_label',
        'is_active',
        'starts_at',
        'ends_at',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function scopeCurrentlyActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($inner) {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($inner) {
                $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }
}
