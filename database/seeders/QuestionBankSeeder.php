<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QuestionBankSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $items = [
        [
            'pertanyaan' => 'Status pekerjaan saat ini',
            'tipe' => 'multiple_choice',
            'pilihan' => ['Bekerja', 'Wiraswasta', 'Melanjutkan pendidikan', 'Mencari kerja', 'Belum memungkinkan bekerja'],
            'is_required' => true,
        ],
        [
            'pertanyaan' => 'Berapa bulan setelah lulus mendapatkan pekerjaan pertama?',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
        ],
        [
            'pertanyaan' => 'Kinerja alumni kami (penilaian pengguna)',
            'tipe' => 'likert',
            'pilihan' => ['Sangat baik', 'Baik', 'Cukup', 'Kurang'],
            'is_required' => true,
        ],
        [
            'pertanyaan' => 'Bidang industri tempat bekerja',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
        ],
        [
            'pertanyaan' => 'Harapan pengembangan kompetensi',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
        ],
        [
            'pertanyaan' => 'Nama lengkap',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'NIK',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Alamat',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'No HP',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'NIM',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Fakultas',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Program Studi',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Tahun Lulus',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Email',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Profil',
            ],
        ],
        [
            'pertanyaan' => 'Status setelah lulus',
            'tipe' => 'select',
            'pilihan' => ['Bekerja', 'Wirausaha', 'Melanjutkan pendidikan', 'Mencari kerja', 'Belum memungkinkan bekerja'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Status',
            ],
        ],
        [
            'pertanyaan' => 'Masukan singkat untuk kampus/CDC',
            'tipe' => 'textarea',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Masukan',
            ],
        ],
        [
            'pertanyaan' => 'Mulai mencari kerja (bulan sebelum lulus)',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Mulai mencari kerja (bulan setelah lulus)',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Mendapat pekerjaan sebelum/Γëñ6 bulan?',
            'tipe' => 'radio',
            'pilihan' => ['Iya', 'Tidak'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pekerjaan pertama',
            ],
        ],
        [
            'pertanyaan' => 'Berapa bulan sampai mendapat pekerjaan',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pekerjaan pertama',
            ],
        ],
        [
            'pertanyaan' => 'Pendapatan per bulan',
            'tipe' => 'select',
            'pilihan' => ['<1 juta', '1-3 juta', '3-5 juta', '>5 juta'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Tingkat tempat kerja',
            'tipe' => 'select',
            'pilihan' => ['Lokal', 'Nasional', 'Multinasional', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Lokasi bekerja (provinsi/kabupaten)',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Lokasi',
            ],
        ],
        [
            'pertanyaan' => 'Jenis perusahaan/instansi',
            'tipe' => 'select',
            'pilihan' => ['Instansi pemerintah', 'BUMN/BUMD', 'Organisasi multilateral', 'LSM', 'Perusahaan swasta', 'Wiraswasta', 'Lembaga/ Yayasan', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Nama perusahaan dan pimpinan',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Kontak perusahaan/pimpinan',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Cara mencari pekerjaan (multi)',
            'tipe' => 'checkbox',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Jumlah perusahaan dilamar/respon/wawancara',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Posisi/Jabatan dan kesesuaian bidang',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Kesesuaian pendidikan dengan pekerjaan',
            'tipe' => 'select',
            'pilihan' => ['Sangat sesuai', 'Sesuai', 'Cukup sesuai', 'Kurang sesuai'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'bekerja',
                'category' => 'Karier',
            ],
        ],
        [
            'pertanyaan' => 'Nama dan kontak usaha',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'wiraswasta',
                'category' => 'Wirausaha',
            ],
        ],
        [
            'pertanyaan' => 'Bidang dan jenis usaha',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'wiraswasta',
                'category' => 'Wirausaha',
            ],
        ],
        [
            'pertanyaan' => 'Tingkat usaha (lokal/nasional)',
            'tipe' => 'select',
            'pilihan' => ['Lokal', 'Nasional', 'Internasional'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'wiraswasta',
                'category' => 'Wirausaha',
            ],
        ],
        [
            'pertanyaan' => 'Kesesuaian usaha dengan pendidikan',
            'tipe' => 'select',
            'pilihan' => ['Sangat sesuai', 'Sesuai', 'Cukup sesuai', 'Kurang sesuai'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'wiraswasta',
                'category' => 'Wirausaha',
            ],
        ],
        [
            'pertanyaan' => 'Lokasi studi lanjut (DN/LN)',
            'tipe' => 'select',
            'pilihan' => ['Dalam negeri', 'Luar negeri'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'melanjutkan',
                'category' => 'Studi lanjut',
            ],
        ],
        [
            'pertanyaan' => 'Sumber biaya studi',
            'tipe' => 'select',
            'pilihan' => ['Biaya sendiri', 'Beasiswa', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'melanjutkan',
                'category' => 'Studi lanjut',
            ],
        ],
        [
            'pertanyaan' => 'Nama PT dan prodi tujuan',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'melanjutkan',
                'category' => 'Studi lanjut',
            ],
        ],
        [
            'pertanyaan' => 'Tanggal masuk studi lanjut',
            'tipe' => 'date',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'melanjutkan',
                'category' => 'Studi lanjut',
            ],
        ],
        [
            'pertanyaan' => 'Alasan melanjutkan studi',
            'tipe' => 'select',
            'pilihan' => ['Tuntutan profesi', 'Beasiswa', 'Prestise', 'Belum ingin bekerja', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'melanjutkan',
                'category' => 'Studi lanjut',
            ],
        ],
        [
            'pertanyaan' => 'Mulai mencari kerja (bulan sebelum lulus)',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'mencari',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Mulai mencari kerja (bulan setelah lulus)',
            'tipe' => 'number',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'mencari',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Cara mencari kerja (multi)',
            'tipe' => 'checkbox',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'mencari',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Jumlah lamaran/respon/wawancara',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'mencari',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Aktif mencari 4 minggu terakhir?',
            'tipe' => 'select',
            'pilihan' => ['Tidak', 'Tidak, menunggu hasil', 'Ya, akan mulai bekerja 2 minggu', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'mencari',
                'category' => 'Pencarian kerja',
            ],
        ],
        [
            'pertanyaan' => 'Penilaian kompetensi individu (Etika, Keahlian, Bahasa, TI, Komunikasi, Kerjasama, Pengembangan)',
            'tipe' => 'radio',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Kompetensi',
            ],
        ],
        [
            'pertanyaan' => 'Penilaian kompetensi pembelajaran (Perkuliahan, Demonstrasi, Riset, Magang, Praktikum, Kerja lapangan, Diskusi)',
            'tipe' => 'radio',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Kompetensi',
            ],
        ],
        [
            'pertanyaan' => 'Sumber dana pembiayaan kuliah',
            'tipe' => 'select',
            'pilihan' => ['Biaya sendiri/keluarga', 'Beasiswa ADik', 'Beasiswa KIP-K', 'Beasiswa PPA', 'Beasiswa Afirmasi', 'Beasiswa perusahaan/swasta', 'Lainnya'],
            'is_required' => false,
            'metadata' => [
                'target' => 'alumni',
                'statusGroup' => 'umum',
                'category' => 'Pendanaan',
            ],
        ],
        [
            'pertanyaan' => 'Nama perusahaan/instansi',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Profil organisasi',
            ],
        ],
        [
            'pertanyaan' => 'Bidang industri',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Profil organisasi',
            ],
        ],
        [
            'pertanyaan' => 'Nama PIC & jabatan',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Profil organisasi',
            ],
        ],
        [
            'pertanyaan' => 'Kontak (email/telepon)',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Profil organisasi',
            ],
        ],
        [
            'pertanyaan' => 'Kota/kabupaten',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Profil organisasi',
            ],
        ],
        [
            'pertanyaan' => 'Kinerja alumni kami',
            'tipe' => 'textarea',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Penilaian',
            ],
        ],
        [
            'pertanyaan' => 'Kompetensi paling menonjol',
            'tipe' => 'textarea',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Penilaian',
            ],
        ],
        [
            'pertanyaan' => 'Area pengembangan yang diharapkan',
            'tipe' => 'textarea',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Penilaian',
            ],
        ],
        [
            'pertanyaan' => 'Jumlah alumni yang direkrut',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Kebutuhan rekrutmen',
            ],
        ],
        [
            'pertanyaan' => 'Peran atau divisi yang dibutuhkan',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Kebutuhan rekrutmen',
            ],
        ],
        [
            'pertanyaan' => 'Waktu kebutuhan tenaga kerja',
            'tipe' => 'text',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Kebutuhan rekrutmen',
            ],
        ],
        [
            'pertanyaan' => 'Catatan tambahan/masukan untuk kampus',
            'tipe' => 'textarea',
            'pilihan' => null,
            'is_required' => false,
            'metadata' => [
                'target' => 'pengguna',
                'statusGroup' => 'pengguna',
                'category' => 'Masukan',
            ],
        ],
        ];

        foreach ($items as $item) {
            DB::table('question_bank_items')->updateOrInsert(
                ['pertanyaan' => $item['pertanyaan']],
                [
                    'tipe' => $item['tipe'],
                    'pilihan' => !empty($item['pilihan']) ? json_encode($item['pilihan']) : null,
                    'is_required' => $item['is_required'] ?? false,
                    'metadata' => isset($item['metadata']) ? json_encode($item['metadata']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
