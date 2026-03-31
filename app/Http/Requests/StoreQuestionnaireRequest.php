<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'status' => $this->input('status') ?? 'draft',
        ];

        if ($this->has('title')) {
            $merge['judul'] = $this->input('title');
        } elseif ($this->has('judul')) {
            $merge['judul'] = $this->input('judul');
        }

        if ($this->has('description')) {
            $merge['deskripsi'] = $this->input('description');
        } elseif ($this->has('deskripsi')) {
            $merge['deskripsi'] = $this->input('deskripsi');
        }

        if ($this->has('chipText')) {
            $merge['chip_text'] = $this->input('chipText');
        } elseif ($this->has('chip_text')) {
            $merge['chip_text'] = $this->input('chip_text');
        }

        if ($this->has('estimatedTime')) {
            $merge['estimated_time'] = $this->input('estimatedTime');
        } elseif ($this->has('estimated_time')) {
            $merge['estimated_time'] = $this->input('estimated_time');
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:draft,published,closed'],
            'audience' => ['nullable', 'string', 'in:alumni,pengguna,umum'],
            'chip_text' => ['nullable', 'string', 'max:255'],
            'estimated_time' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'extra_questions' => ['nullable', 'array'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'questions' => ['array'],
            'questions.*.pertanyaan' => ['required_with:questions', 'string'],
            'questions.*.tipe' => ['required_with:questions', 'string', 'in:text,number,multiple_choice,likert'],
            'questions.*.pilihan' => ['nullable', 'array'],
            'questions.*.pilihan.*' => ['string'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.urutan' => ['integer', 'min:0'],
        ];
    }
}
