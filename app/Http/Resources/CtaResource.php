<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CtaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tag' => $this->tag,
            'title' => $this->title,
            'highlight' => $this->highlight,
            'subtitle' => $this->subtitle,
            'chips' => $this->chips ?? [],
            'primary' => $this->primary ?? null,
            'secondary' => $this->secondary ?? null,
            'stats' => $this->stats ?? null,
            'order' => $this->order ?? 0,
        ];
    }
}
