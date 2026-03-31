<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Alumni;
use App\Models\Response;
use Carbon\Carbon;

class ExistingAlumniResponseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pick 10 random existing Alumni
        $alumniList = Alumni::inRandomOrder()->take(10)->get();

        if ($alumniList->count() < 10) {
            $this->command->warn('Not enough alumni found. Only ' . $alumniList->count() . ' seeded.');
        }

        $questionnaireId = 1;

        $companies = [
            'PT Global Digital',
            'Bank Mandiri Persero',
            'Dinas PU Binamarga',
            'Shopee Indonesia',
            'RS Hermina',
            'CV Maju Jaya',
            'Yayasan Pendidikan',
            'PT Astra International',
            'Kementerian Kominfo',
            'Gojek Indonesia'
        ];

        $companyTypes = [
            'Perusahaan Swasta',
            'BUMN/BUMD',
            'Instansi Pemerintah',
            'Perusahaan Swasta',
            'Lainnya',
            'Wiraswasta',
            'Organisasi Non-Profit',
            'Perusahaan Swasta',
            'Instansi Pemerintah',
            'Perusahaan Swasta'
        ];

        $positions = [
            'Software Engineer',
            'Teller',
            'Staff Admin',
            'Product Manager',
            'Perawat',
            'Owner',
            'Guru',
            'Sales Executive',
            'Data Analyst',
            'Driver'
        ];

        foreach ($alumniList as $index => $alumni) {
            // Varied months: 0 to 9
            $months = $index;

            $formData = [
                'nama' => $alumni->nama,
                'nim' => $alumni->nim,
                'email' => $alumni->email,
                'tahun' => $alumni->tahun_lulus,
                'prodi' => $alumni->prodi,
                'fakultas' => $alumni->fakultas,
                'status' => 'bekerja',

                // Varied Data
                'bekerja_bulanDapat' => $months,
                'bekerja_bulanTidak' => 0,
                'bekerja_jenisPerusahaan' => $companyTypes[$index % count($companyTypes)],
                'bekerja_namaPerusahaan' => $companies[$index % count($companies)],
                'bekerja_posisi' => $positions[$index % count($positions)],
                'bekerja_pendapatan' => rand(4500000, 20000000),
                'bekerja_kesesuaianBidang' => ($index % 3 == 0) ? 'Sangat sesuai' : (($index % 2 == 0) ? 'Sesuai' : 'Kurang sesuai'),
                'bekerja_caraMencari' => ($index % 2 == 0) ? 'Melamar ke perusahaan' : 'Mencari lewat internet/iklan online',
                'bekerja_tingkatTempatKerja' => ($index % 3 == 0) ? 'Multinasional/Internasional' : 'Nasional/Wiraswasta berbadan hukum',
                'bekerja_provinsi' => 'Jawa Barat',
                'bekerja_kabupaten' => 'Bandung',
                'bekerja_pendidikanSesuai' => 'Tingkat yang sama',
            ];

            Response::create([
                'alumni_id' => $alumni->id,
                'questionnaire_id' => $questionnaireId,
                'attempt_ke' => 1,
                'form_data' => $formData,
                'created_at' => Carbon::now()->subDays(rand(0, 30)), // Randomize submit time slightly
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
