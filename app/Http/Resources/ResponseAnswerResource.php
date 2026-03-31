<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ResponseAnswerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->question_id,
            'question' => $this->whenLoaded('question', fn () => $this->question?->pertanyaan),
            'jawaban' => $this->jawaban,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
