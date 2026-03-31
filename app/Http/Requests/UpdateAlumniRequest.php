<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $alumniId = $this->route('alumni')?->id;

        return [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'nim' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('alumnis', 'nim')->ignore($alumniId),
            ],
            'nik' => ['sometimes', 'nullable', 'string', 'max:50'],
            'prodi' => ['sometimes', 'required', 'string', 'max:255'],
            'fakultas' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tahun_masuk' => ['sometimes', 'nullable', 'digits:4'],
            'tahun_lulus' => ['sometimes', 'required', 'digits:4'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('alumnis', 'email')->ignore($alumniId),
            ],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'tanggal_lahir' => ['sometimes', 'nullable', 'date'],
            'foto' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sent' => ['sometimes', 'nullable', 'boolean'],
            'status_pekerjaan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
