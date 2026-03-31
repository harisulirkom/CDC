<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicAlumniResource extends JsonResource
{
    public function toArray($request): array
    {
        $nik = $this->nik ?? $this->nik_alumni ?? $this->no_ktp ?? $this->ktp ?? null;
        $alamat = $this->alamat ?? $this->alamat_alumni ?? $this->alamat_mahasiswa ?? $this->alamat_lengkap ?? $this->alamat_rumah ?? null;
        $noHp = $this->no_hp ?? $this->hp ?? $this->noHp ?? $this->nomor_hp ?? $this->phone ?? null;

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'name' => $this->nama,
            'nama_alumni' => $this->nama,
            'nim' => $this->nim,
            'nomor_induk' => $this->nim,
            'nik' => $nik,
            'nik_alumni' => $nik,
            'no_ktp' => $this->no_ktp ?? $nik,
            'ktp' => $this->ktp ?? $nik,
            'no_hp' => $noHp,
            'hp' => $noHp,
            'noHp' => $noHp,
            'nomor_hp' => $noHp,
            'phone' => $noHp,
            'alamat' => $alamat,
            'alamat_alumni' => $alamat,
            'alamat_mahasiswa' => $alamat,
            'alamat_lengkap' => $alamat,
            'alamat_rumah' => $alamat,
            'prodi' => $this->prodi,
            'program_study_name' => $this->program_study_name ?? $this->prodi,
            'fakultas' => $this->fakultas ?? null,
            'nama_fakultas' => $this->nama_fakultas ?? ($this->fakultas ?? null),
            'tahun_lulus' => $this->tahun_lulus,
            'tahunLulus' => $this->tahun_lulus,
            'tahun_masuk' => $this->tahun_masuk ?? null,
            'tahunMasuk' => $this->tahun_masuk ?? null,
        ];
    }
}
