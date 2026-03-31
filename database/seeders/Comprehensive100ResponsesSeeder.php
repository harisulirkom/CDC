<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Alumni;
use App\Models\Response;
use Carbon\Carbon;

class Comprehensive100ResponsesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        // Get 100 random alumni
        $alumniList = Alumni::inRandomOrder()->take(100)->get();

        if ($alumniList->count() < 100) {
            $this->command->warn('Not enough alumni. Only ' . $alumniList->count() . ' will be seeded.');
        }

        $questionnaireId = 1;

        // Status distribution: 60 Bekerja, 25 Wirausaha, 10 Mencari, 5 Melanjutkan
        $statusDistribution = array_merge(
            array_fill(0, 60, 'bekerja'),
            array_fill(0, 25, 'wiraswasta'),
            array_fill(0, 10, 'mencari'),
            array_fill(0, 5, 'melanjutkan')
        );
        shuffle($statusDistribution);

        // Data pools for variety
        $companies = [
            'PT Telkom Indonesia',
            'Bank Mandiri',
            'PT Astra International',
            'Gojek Indonesia',
            'Tokopedia',
            'Shopee Indonesia',
            'Bukalapak',
            'PT Pertamina',
            'PT PLN',
            'Kementerian Keuangan',
            'Dinas Pendidikan',
            'RS Hasan Sadikin',
            'PT Unilever',
            'PT Indofood',
            'PT Garuda Indonesia',
            'PT Bank BCA',
            'PT Djarum',
            'PT HM Sampoerna',
            'Google Indonesia',
            'Microsoft Indonesia'
        ];

        $companyTypes = [
            'Perusahaan Swasta',
            'BUMN/BUMD',
            'Instansi Pemerintah',
            'Organisasi Non-Profit',
            'Lainnya'
        ];

        $positions = [
            'Software Engineer',
            'Data Analyst',
            'Product Manager',
            'Marketing Manager',
            'HR Manager',
            'Finance Officer',
            'Sales Executive',
            'Customer Service',
            'Quality Assurance',
            'Business Analyst',
            'Project Manager',
            'UI/UX Designer',
            'Content Writer',
            'Graphic Designer',
            'Accountant',
            'Admin Staff'
        ];

        $wirausahaTypes = [
            'Perdagangan',
            'Jasa',
            'Manufaktur/Produksi',
            'Teknologi/Startup',
            'Kreatif/Seni',
            'Kuliner',
            'Fashion',
            'Pendidikan'
        ];

        $wirausahaBidang = [
            'Kuliner',
            'Fashion',
            'Pendidikan',
            'Kesehatan',
            'Konstruksi',
            'Otomotif',
            'Desain/Media',
            'TI/Software',
            'E-commerce',
            'Konsultan'
        ];

        $universities = [
            'Universitas Indonesia',
            'Institut Teknologi Bandung',
            'Universitas Gadjah Mada',
            'Institut Pertanian Bogor',
            'Universitas Airlangga',
            'Universitas Padjadjaran',
            'Universitas Diponegoro',
            'Institut Teknologi Sepuluh Nopember',
            'National University of Singapore',
            'University of Melbourne'
        ];

        $studyPrograms = [
            'Magister Manajemen',
            'Magister Teknik Informatika',
            'Magister Akuntansi',
            'Magister Ilmu Komunikasi',
            'Magister Psikologi',
            'PhD Computer Science',
            'Magister Teknik Sipil',
            'Magister Kesehatan Masyarakat'
        ];

        foreach ($alumniList as $index => $alumni) {
            $status = $statusDistribution[$index] ?? 'bekerja';

            // Base data
            $formData = [
                'nama' => $alumni->nama,
                'nim' => $alumni->nim,
                'email' => $alumni->email,
                'tahun' => $alumni->tahun_lulus,
                'prodi' => $alumni->prodi,
                'fakultas' => $alumni->fakultas,
                'status' => $status,
            ];

            // Fill branching fields based on status
            if ($status === 'bekerja') {
                $waitMonths = rand(0, 12);
                $salary = rand(4500000, 20000000);

                $formData = array_merge($formData, [
                    'bekerja_mulaiSebelum' => rand(0, 1) ? rand(1, 6) : 0,
                    'bekerja_mulaiSetelah' => rand(0, 1) ? rand(1, 6) : 0,
                    'bekerja_lebihCepat6Bulan' => $waitMonths <= 6 ? 'Ya' : 'Tidak',
                    'bekerja_bulanDapat' => $waitMonths,
                    'bekerja_bulanTidak' => 0,
                    'bekerja_pendapatan' => $salary,
                    'bekerja_tingkatTempatKerja' => $faker->randomElement([
                        'Lokal/Wilayah/Wiraswasta tidak berbadan hukum',
                        'Nasional/Wiraswasta berbadan hukum',
                        'Multinasional/Internasional'
                    ]),
                    'bekerja_lokasiDetail' => $faker->city,
                    'bekerja_provinsi' => 'Jawa Barat',
                    'bekerja_kabupaten' => 'Bandung',
                    'bekerja_jenisPerusahaan' => $faker->randomElement($companyTypes),
                    'bekerja_namaPerusahaan' => $faker->randomElement($companies),
                    'bekerja_namaPimpinan' => $faker->name,
                    'bekerja_telpPerusahaan' => $faker->phoneNumber,
                    'bekerja_caraMencari' => $faker->randomElement([
                        'Melamar ke perusahaan',
                        'Mencari lewat internet/iklan online',
                        'Dihubungi oleh perusahaan',
                        'Melalui relasi/keluarga',
                        'Membangun jejaring (networking)'
                    ]),
                    'bekerja_perusahaanLamar' => rand(3, 20),
                    'bekerja_perusahaanRespon' => rand(1, 10),
                    'bekerja_perusahaanWawancara' => rand(1, 5),
                    'bekerja_posisi' => $faker->randomElement($positions),
                    'bekerja_kesesuaianBidang' => $faker->randomElement([
                        'Sangat sesuai',
                        'Sesuai',
                        'Cukup sesuai',
                        'Kurang sesuai'
                    ]),
                    'bekerja_pendidikanSesuai' => $faker->randomElement([
                        'Setingkat lebih tinggi',
                        'Tingkat yang sama',
                        'Setingkat lebih rendah',
                        'Tidak perlu pendidikan tinggi'
                    ]),
                ]);
            } elseif ($status === 'wiraswasta') {
                $formData = array_merge($formData, [
                    'wira_namaPerusahaan' => 'CV ' . $faker->company,
                    'wira_telpPerusahaan' => $faker->phoneNumber,
                    'wira_jenisPerusahaan' => $faker->randomElement($wirausahaTypes),
                    'wira_bidang' => $faker->randomElement($wirausahaBidang),
                    'wira_tingkat' => $faker->randomElement(['Lokal', 'Nasional']), // Merata lokal/nasional
                    'wira_kesesuaian' => $faker->randomElement([
                        'Sangat sesuai',
                        'Sesuai',
                        'Kurang sesuai'
                    ]),
                    'wira_pendidikan' => $faker->randomElement([
                        'Sangat menunjang',
                        'Menunjang',
                        'Kurang menunjang'
                    ]),
                ]);
            } elseif ($status === 'mencari') {
                $lamaMencari = rand(1, 12); // Max 12 bulan

                $formData = array_merge($formData, [
                    'mencari_mulaiSebelum' => rand(0, 1) ? rand(1, 6) : 0,
                    'mencari_mulaiSetelah' => rand(0, 1) ? rand(1, 6) : 0,
                    'mencari_cara' => $faker->randomElement([
                        'Job portal',
                        'Website perusahaan',
                        'LinkedIn',
                        'Job fair',
                        'Relasi',
                        'Media sosial'
                    ]),
                    'mencari_perusahaanLamar' => 10, // Jumlah lamaran 10
                    'mencari_perusahaanRespon' => rand(1, 5),
                    'mencari_perusahaanWawancara' => rand(0, 3),
                    'mencari_aktif4Minggu' => $faker->randomElement(['Ya', 'Tidak']),
                ]);
            } elseif ($status === 'melanjutkan') {
                $isLokal = rand(1, 10) <= 8; // 80% lokal, 20% luar negeri

                $formData = array_merge($formData, [
                    'studi_lokasi' => $isLokal ? 'Dalam Negeri' : 'Luar Negeri',
                    'studi_sumberBiaya' => $faker->randomElement([
                        'Biaya Sendiri',
                        'Beasiswa Pemerintah',
                        'Beasiswa Swasta/Institusi'
                    ]),
                    'studi_namaPt' => $faker->randomElement($universities),
                    'studi_prodi' => $faker->randomElement($studyPrograms),
                    'studi_tanggalMasuk' => Carbon::now()->subMonths(rand(1, 12))->format('Y-m-d'),
                    'studi_alasan' => $faker->randomElement([
                        'Tuntutan profesi',
                        'Mendalami ilmu',
                        'Lainnya'
                    ]),
                ]);
            }

            // Add common fields
            $formData['kompetensi_individu'] = sprintf(
                'Etika:%d;Keahlian:%d;Bhs Inggris:%d;Teknologi Informasi:%d;Komunikasi:%d;Kerja Sama:%d;Pengembangan Diri:%d',
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5)
            );
            $formData['kompetensi_pembelajaran'] = sprintf(
                'Perkuliahan:%d;Pembimbingan:%d;Metode Pengajaran:%d;Sarana:%d;Integritas:%d;Keluasan Ilmu:%d;Kesempatan Riset:%d',
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5),
                rand(3, 5)
            );
            $formData['sumberDana'] = $faker->randomElement([
                'Biaya Sendiri/Keluarga',
                'Beasiswa Bidikmisi/KIP-K',
                'Beasiswa PPA',
                'Beasiswa Lainnya'
            ]);

            // Create response
            Response::create([
                'alumni_id' => $alumni->id,
                'questionnaire_id' => $questionnaireId,
                'attempt_ke' => 1,
                'form_data' => $formData,
                'created_at' => Carbon::now()->subDays(rand(0, 60)),
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info('Successfully seeded ' . $alumniList->count() . ' questionnaire responses!');
    }
}
