<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

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

        if ($this->has('extraQuestions')) {
            $merge['extra_questions'] = $this->input('extraQuestions');
        } elseif ($this->has('extra_questions')) {
            $merge['extra_questions'] = $this->input('extra_questions');
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'judul' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,published,closed'],
            'audience' => ['nullable', 'string', 'in:alumni,pengguna,umum'],
            'chip_text' => ['nullable', 'string', 'max:255'],
            'estimated_time' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'extra_questions' => ['nullable', 'array'],
            'extraQuestions' => ['nullable', 'array'],
            'tanggal_mulai' => ['sometimes', 'nullable', 'date'],
            'tanggal_akhir' => ['sometimes', 'nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'questions' => ['nullable', 'array'],
            'questions.*' => ['array'],
            'questions.*.pertanyaan' => ['sometimes', 'string'],
            'questions.*.label' => ['sometimes', 'string'],
            'questions.*.tipe' => ['sometimes', 'string'],
            'questions.*.type' => ['sometimes', 'string'],
            'questions.*.pilihan' => ['nullable', 'array'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.is_required' => ['sometimes', 'boolean'],
            'questions.*.isRequired' => ['sometimes', 'boolean'],
            'questions.*.required' => ['sometimes', 'boolean'],
            'questions.*.status_condition' => ['nullable', 'string'],
            'questions.*.statusCondition' => ['nullable', 'string'],
            'questions.*.urutan' => ['sometimes', 'integer'],
        ];
    }
}
