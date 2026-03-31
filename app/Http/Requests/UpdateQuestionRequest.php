<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pertanyaan' => ['sometimes', 'required', 'string'],
            'tipe' => ['sometimes', 'required', 'string', 'in:text,number,multiple_choice,likert'],
            'pilihan' => ['sometimes', 'nullable', 'array'],
            'pilihan.*' => ['string'],
            'is_required' => ['sometimes', 'boolean'],
            'urutan' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
