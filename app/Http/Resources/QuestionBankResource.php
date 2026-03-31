<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionBankResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'pertanyaan' => $this->pertanyaan,
            'tipe' => $this->tipe,
            'pilihan' => $this->pilihan ?? [],
            'is_required' => $this->is_required,
            'isRequired' => $this->is_required,
            'metadata' => $this->metadata ?? [],
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
