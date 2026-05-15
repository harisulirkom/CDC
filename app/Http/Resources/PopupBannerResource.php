<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PopupBannerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'imagePath' => $this->image_path,
            'imageUrl' => $this->resolveImageUrl($request),
            'externalImageUrl' => $this->image_url,
            'linkUrl' => $this->link_url,
            'buttonLabel' => $this->button_label,
            'isActive' => (bool) $this->is_active,
            'startsAt' => $this->starts_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'sortOrder' => (int) ($this->sort_order ?? 0),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function resolveImageUrl($request): ?string
    {
        if ($this->image_path) {
            return '/api/popup-banners/' . $this->id . '/image';
        }

        if ($this->image_url) {
            return $this->normalizeExternalImageUrl((string) $this->image_url);
        }

        return null;
    }

    protected function normalizeExternalImageUrl(string $url): string
    {
        if (preg_match('#drive\.google\.com/file/d/([^/]+)#', $url, $matches)) {
            return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1600';
        }

        if (preg_match('#drive\.google\.com/open\?id=([^&]+)#', $url, $matches)) {
            return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1600';
        }

        return $url;
    }
}
