<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pertanyaan' => ['required', 'string'],
            'tipe' => ['required', 'string', 'in:text,number,multiple_choice,likert'],
            'pilihan' => ['nullable', 'array'],
            'pilihan.*' => ['string'],
            'is_required' => ['boolean'],
            'urutan' => ['integer', 'min:0'],
        ];
    }
}
