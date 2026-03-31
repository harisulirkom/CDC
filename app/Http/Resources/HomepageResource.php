<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HomepageResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'hero' => $data['hero'] ?? null,
            'sections' => $data['sections'] ?? [],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
