<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50', 'unique:alumnis,nim'],
            'nik' => ['nullable', 'string', 'max:50'],
            'prodi' => ['required', 'string', 'max:255'],
            'fakultas' => ['nullable', 'string', 'max:255'],
            'tahun_masuk' => ['nullable', 'digits:4'],
            'tahun_lulus' => ['required', 'digits:4'],
            'email' => ['required', 'email', 'max:255', 'unique:alumnis,email'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'tanggal_lahir' => ['nullable', 'date'],
            'foto' => ['nullable', 'string', 'max:255'],
            'sent' => ['nullable', 'boolean'],
            'status_pekerjaan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
