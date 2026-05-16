<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AlumniHubResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content ?? [],
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
