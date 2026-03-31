<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use Illuminate\Database\Seeder;

class QuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedQuestionnaire(
            audience: 'alumni',
            title: 'Isi kuisioner alumni',
            chipText: 'Tracer Study 2025 - Kuisioner sudah dibuka',
            description: 'Partisipasi Anda mendukung peningkatan kurikulum, kerjasama industri, dan layanan karier mahasiswa.',
            estimated: '+/-5 menit',
            active: true,
            questions: [
                ['pertanyaan' => 'Nama lengkap', 'tipe' => 'text', 'is_required' => true, 'urutan' => 0],
                ['pertanyaan' => 'NIM', 'tipe' => 'text', 'is_required' => true, 'urutan' => 1],
                [
                    'pertanyaan' => 'Status pekerjaan saat ini',
                    'tipe' => 'multiple_choice',
                    'pilihan' => ['Bekerja', 'Wiraswasta', 'Melanjutkan studi', 'Mencari kerja'],
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'pertanyaan' => 'Berapa bulan setelah lulus mendapatkan pekerjaan pertama?',
                    'tipe' => 'number',
                    'is_required' => false,
                    'urutan' => 3,
                ],
                [
                    'pertanyaan' => 'Pendapatan/gaji awal (angka saja)',
                    'tipe' => 'number',
                    'is_required' => false,
                    'urutan' => 4,
                ],
            ]
        );

        $this->seedQuestionnaire(
            audience: 'pengguna',
            title: 'Kuisioner pengguna alumni',
            chipText: 'Tracer Study - Kuisioner pengguna dibuka',
            description: 'Berikan penilaian terhadap performa alumni yang bekerja di organisasi Anda.',
            estimated: '+/-4 menit',
            active: true,
            questions: [
                ['pertanyaan' => 'Nama organisasi', 'tipe' => 'text', 'is_required' => true, 'urutan' => 0],
                ['pertanyaan' => 'Nama PIC', 'tipe' => 'text', 'is_required' => true, 'urutan' => 1],
                [
                    'pertanyaan' => 'Bagaimana kinerja alumni kami?',
                    'tipe' => 'multiple_choice',
                    'pilihan' => ['Sangat baik', 'Baik', 'Cukup', 'Kurang'],
                    'is_required' => true,
                    'urutan' => 2,
                ],
                [
                    'pertanyaan' => 'Bidang pekerjaan yang paling dibutuhkan',
                    'tipe' => 'text',
                    'is_required' => false,
                    'urutan' => 3,
                ],
            ]
        );

        $this->seedQuestionnaire(
            audience: 'umum',
            title: 'Kuisioner umum',
            chipText: 'Kuisioner umum dibuka',
            description: 'Gunakan untuk survei non-alumni.',
            estimated: '+/-3 menit',
            active: false,
            questions: [
                ['pertanyaan' => 'Nama', 'tipe' => 'text', 'is_required' => true, 'urutan' => 0],
                ['pertanyaan' => 'Instansi/organisasi', 'tipe' => 'text', 'is_required' => false, 'urutan' => 1],
            ]
        );
    }

    protected function seedQuestionnaire(string $audience, string $title, string $chipText, string $description, string $estimated, bool $active, array $questions): void
    {
        $existing = Questionnaire::where('audience', $audience)->first();

        if ($existing) {
            // Ensure only one active per audience
            if ($active) {
                Questionnaire::where('audience', $audience)->update(['is_active' => false]);
            }
            $existing->update([
                'judul' => $title,
                'deskripsi' => $description,
                'chip_text' => $chipText,
                'estimated_time' => $estimated,
                'audience' => $audience,
                'is_active' => $active,
                'status' => $existing->status ?? 'published',
            ]);
            $existing->questions()->delete();
            $existing->questions()->createMany($questions);
            return;
        }

        if ($active) {
            Questionnaire::where('audience', $audience)->update(['is_active' => false]);
        }

        $questionnaire = Questionnaire::create([
            'judul' => $title,
            'deskripsi' => $description,
            'status' => 'published',
            'audience' => $audience,
            'chip_text' => $chipText,
            'estimated_time' => $estimated,
            'is_active' => $active,
        ]);

        $questionnaire->questions()->createMany($questions);
    }
}
