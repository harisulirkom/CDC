<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AlumniResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'name' => $this->nama,
            'nama_alumni' => $this->nama,
            'nama_mhs' => $this->nama,
            'nim' => $this->nim,
            'nomor_induk' => $this->nim,
            'nik' => $this->nik ?? null,
            'no_ktp' => $this->no_ktp ?? ($this->nik ?? null),
            'ktp' => $this->ktp ?? ($this->nik ?? null),
            'hp' => $this->no_hp ?? $this->hp ?? null,
            'hp_alumni' => $this->hp_alumni ?? $this->no_hp ?? $this->hp ?? null,
            'nomor_hp' => $this->nomor_hp ?? $this->no_hp ?? $this->hp ?? null,
            'no_hp' => $this->no_hp ?? null,
            'noHp' => $this->no_hp ?? $this->hp ?? null,
            'phone' => $this->no_hp ?? $this->hp ?? null,
            'alamat' => $this->alamat ?? null,
            'alamat_alumni' => $this->alamat_alumni ?? ($this->alamat ?? null),
            'alamat_mahasiswa' => $this->alamat_mahasiswa ?? ($this->alamat ?? null),
            'tanggal_lahir' => $this->tanggal_lahir?->toDateString(),
            'dob' => $this->tanggal_lahir?->toDateString(),
            'foto' => $this->foto ?? null,
            'photoUrl' => $this->foto ?? null,
            'prodi' => $this->prodi,
            'program_study_name' => $this->program_study_name ?? $this->prodi,
            'jurusan' => $this->jurusan ?? $this->prodi,
            'fakultas' => $this->fakultas ?? null,
            'nama_fakultas' => $this->nama_fakultas ?? ($this->fakultas ?? null),
            'tahun_lulus' => $this->tahun_lulus,
            'tahunLulus' => $this->tahun_lulus,
            'tahun_kelulusan' => $this->tahun_kelulusan ?? $this->tahun_lulus,
            'tahun_lulus_alumni' => $this->tahun_lulus_alumni ?? $this->tahun_lulus,
            'tahun_masuk' => $this->tahun_masuk ?? null,
            'tahunMasuk' => $this->tahun_masuk ?? null,
            'graduationYear' => $this->tahun_lulus,
            'email' => $this->email,
            'sent' => (bool) ($this->sent ?? false),
            'status_pekerjaan' => $this->status_pekerjaan,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
