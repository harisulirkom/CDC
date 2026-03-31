<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Alumni;
use App\Models\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpecificWorkingAlumniSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Ensure questionnaire ID 1 exists (from previous tinker check)
        $questionnaireId = 1;

        $companies = ['PT Teknologi Maju', 'BUMN Persero', 'Dinas Pendidikan', 'Startup Lokal', 'Rumah Sakit Umum'];
        $companyTypes = ['Perusahaan Swasta', 'BUMN/BUMD', 'Instansi Pemerintah', 'Wiraswasta', 'Organisasi Non-Profit'];

        for ($i = 0; $i < 10; $i++) {
            // 1. Create Alumni
            $alumni = Alumni::create([
                'nama' => $faker->name,
                'nim' => 'TEST' . date('Y') . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'nik' => $faker->nik,
                'prodi' => 'Teknik Informatika',
                'fakultas' => 'Teknik',
                'tahun_masuk' => 2018,
                'tahun_lulus' => 2022,
                'email' => $faker->email,
                'no_hp' => $faker->phoneNumber,
                'status_pekerjaan' => 'Working', // Internal status
            ]);

            // Vary months to get job: 0, 1, 2, ..., 9
            $months = $i;

            // Construct Form Data (Static Answers)
            $formData = [
                'nama' => $alumni->nama,
                'nim' => $alumni->nim,
                'email' => $alumni->email,
                'tahun' => $alumni->tahun_lulus,
                'prodi' => $alumni->prodi,
                'fakultas' => $alumni->fakultas,
                'status' => 'bekerja',

                // Specific fields requested
                'bekerja_bulanDapat' => $months,
                'bekerja_bulanTidak' => 0,
                'bekerja_jenisPerusahaan' => $companyTypes[$i % count($companyTypes)],
                'bekerja_namaPerusahaan' => $companies[$i % count($companies)],
                'bekerja_pendapatan' => rand(4000000, 15000000),
                'bekerja_kesesuaianBidang' => ($i % 2 == 0) ? 'Sangat sesuai' : 'Kurang sesuai',
                'bekerja_caraMencari' => 'Melamar ke perusahaan',
                'bekerja_provinsi' => 'Jawa Barat',
                'bekerja_kabupaten' => 'Bandung',
            ];

            // 2. Create Response
            Response::create([
                'alumni_id' => $alumni->id,
                'questionnaire_id' => $questionnaireId,
                'attempt_ke' => 1,
                'form_data' => $formData, // Store JSON
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
