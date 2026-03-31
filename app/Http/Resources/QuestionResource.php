<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'question_bank_item_id' => $this->question_bank_item_id,
            'questionBankItemId' => $this->question_bank_item_id,
            'pertanyaan' => $this->pertanyaan,
            'label' => $this->pertanyaan,
            'question' => $this->pertanyaan,
            'tipe' => $this->tipe,
            'type' => $this->tipe,
            'status_condition' => $this->status_condition,
            'statusCondition' => $this->status_condition,
            'pilihan' => $this->pilihan,
            'options' => $this->pilihan,
            'is_required' => $this->is_required,
            'isRequired' => $this->is_required,
            'required' => $this->is_required,
            'urutan' => $this->urutan,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
