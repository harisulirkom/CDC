<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionnaireResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->judul,
            'judul' => $this->judul,
            'description' => $this->deskripsi,
            'deskripsi' => $this->deskripsi,
            'status' => $this->status,
            'audience' => $this->audience_normalized ?? 'alumni',
            'chipText' => $this->chip_text,
            'estimatedTime' => $this->estimated_time,
            'active' => (bool) $this->is_active,
            'tanggal_mulai' => $this->tanggal_mulai?->toIso8601String(),
            'tanggal_akhir' => $this->tanggal_akhir?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'questions' => QuestionResource::collection(
                $this->whenLoaded('questions')
            ),
            'extraQuestions' => $this->extra_questions ?? [],
        ];
    }
}
