<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JobPostingSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            [
                'id' => 'backend-01',
                'title' => 'Software Engineer (Backend)',
                'company' => 'PT Nusantara Digital',
                'company_profile' => 'Perusahaan teknologi yang membangun platform SaaS untuk klien enterprise, budaya kerja kolaboratif dengan fokus pada ownership.',
                'location' => 'Jakarta',
                'work_mode' => 'Hybrid',
                'job_type' => 'Full-time',
                'category' => 'kerja',
                'deadline' => '2025-02-12',
                'summary' => 'Mengembangkan layanan backend yang andal dan scalable untuk produk SaaS dengan praktik clean code, testing, dan observability.',
                'responsibilities' => [
                    'Merancang dan membangun API/RESTful service untuk fitur utama produk.',
                    'Menulis kode yang terukur, teruji (unit/integration), dan terdokumentasi.',
                    'Melakukan code review serta kolaborasi dengan tim frontend dan QA.',
                    'Mengoptimalkan performa query database serta caching.',
                    'Menjaga keamanan aplikasi termasuk autentikasi, otorisasi, dan logging.',
                ],
                'qualifications' => [
                    'education' => 'Minimal S1 Informatika/Sistem Informasi atau bidang terkait.',
                    'experience' => '2-4 tahun pengalaman sebagai Backend Engineer atau Software Engineer.',
                    'skills' => [
                        'Node.js/Express atau NestJS, REST API, JSON',
                        'Basis data SQL/NoSQL (PostgreSQL, MongoDB) dan ORM',
                        'Version control (Git), CI/CD dasar, dan container (Docker)',
                        'Praktik clean architecture, testing, dan troubleshooting',
                        'Komunikasi tim, problem solving, dan integritas tinggi',
                    ],
                    'other' => [
                        'Domisili Jabodetabek diutamakan',
                        'Mampu bekerja hybrid di Jakarta (3 hari onsite)',
                    ],
                ],
                'compensation' => 'Rp10-15 jt/bulan, BPJS Kesehatan/Ketenagakerjaan, tunjangan internet, bonus proyek.',
                'benefits' => ['Asuransi kesehatan', 'WFH allowance', 'Training/sertifikasi', 'Laptop & tools kerja'],
                'apply' => 'Kirim CV + portfolio Github ke careers@nusantaradigital.id dengan subjek: Backend_[Nama]. Portofolio API/service sangat dianjurkan.',
            ],
            [
                'id' => 'data-02',
                'title' => 'Analis Data Pendidikan',
                'company' => 'EduTech Insight',
                'company_profile' => 'Startup edutech yang fokus pada analitik pembelajaran dan rekomendasi kurikulum adaptif.',
                'location' => 'Yogyakarta',
                'work_mode' => 'Onsite',
                'job_type' => 'Full-time',
                'category' => 'kerja',
                'deadline' => '2025-02-18',
                'summary' => 'Mengolah data pembelajaran untuk menghasilkan insight, dashboard, dan rekomendasi kurikulum yang berdampak.',
                'responsibilities' => [
                    'Membersihkan dan memodelkan data siswa/dosen/kurikulum.',
                    'Membangun dashboard KPI pendidikan (retensi, engagement, hasil belajar).',
                    'Berkoordinasi dengan tim produk untuk eksperimen A/B dan rekomendasi konten.',
                    'Menyusun dokumentasi data dan data dictionary.',
                ],
                'qualifications' => [
                    'education' => 'Minimal S1 Statistika, Matematika, Informatika, atau bidang terkait.',
                    'experience' => '1-3 tahun sebagai Data Analyst atau Business Intelligence.',
                    'skills' => [
                        'SQL tingkat lanjut dan visualisasi (Metabase/Tableau/Power BI)',
                        'Python (pandas) untuk eksplorasi dan preprocessing',
                        'Dasar statistik inferensial dan A/B testing',
                        'Komunikasi bisnis dan penyusunan deck insight',
                    ],
                    'other' => ['Terbiasa bekerja dengan data pendidikan atau edutech menjadi nilai plus'],
                ],
                'compensation' => 'Rp8-12 jt/bulan, BPJS, budget training tahunan, makan siang.',
                'benefits' => ['Kelas pengembangan diri', 'Skema cuti tambahan', 'Coaching karier'],
                'apply' => 'Kirim CV + contoh dashboard/portofolio ke talent@edutechinsight.id, subjek: DA_EDU_[Nama].',
            ],
            [
                'id' => 'design-03',
                'title' => 'UI/UX Designer',
                'company' => 'Kreatif Studio',
                'company_profile' => 'Agensi kreatif digital yang mengerjakan produk mobile/web untuk klien internasional.',
                'location' => 'Bandung',
                'work_mode' => 'Remote',
                'job_type' => 'Kontrak 12 bulan',
                'category' => 'kerja',
                'deadline' => '2025-02-22',
                'summary' => 'Merancang pengalaman pengguna end-to-end, dari riset, wireframe, hingga design system siap dev.',
                'responsibilities' => [
                    'Melakukan riset singkat dan merumuskan problem statement.',
                    'Membuat wireframe, user flow, dan prototipe interaktif.',
                    'Menyusun UI kit/design system dan handoff ke developer.',
                    'Berkoordinasi dengan klien untuk iterasi desain.',
                ],
                'qualifications' => [
                    'education' => 'Minimal D3/S1 semua jurusan, diutamakan DKV/Desain Produk/Informatika.',
                    'experience' => 'Minimal 2 tahun di UI/UX dengan portofolio produk digital.',
                    'skills' => [
                        'Figma (auto layout, variant, prototyping)',
                        'Dasar UX writing dan accessibility',
                        'Kolaborasi dengan developer (design token)',
                        'Bahasa Inggris dasar untuk komunikasi klien',
                    ],
                    'other' => ['Portofolio wajib disertakan', 'Ketersediaan untuk weekly sprint review'],
                ],
                'compensation' => 'Rp9-13 jt/bulan (kontrak), tunjangan remote, bonus berbasis proyek.',
                'benefits' => ['Budget tools/plug-in', 'Sesi mentoring desain', 'Flexible working hour'],
                'apply' => 'Kirim CV + portofolio (Figma/website) ke hello@kreatifstudio.id, subjek: UIUX_[Nama].',
            ],
            [
                'id' => 'bd-04',
                'title' => 'Internship - Business Development',
                'company' => 'Mitra Sinergi Group',
                'company_profile' => 'Holding yang bergerak di distribusi FMCG dan kemitraan retail nasional.',
                'location' => 'Semarang',
                'work_mode' => 'Onsite',
                'job_type' => 'Magang (3-6 bulan)',
                'category' => 'magang',
                'deadline' => '2025-02-28',
                'summary' => 'Mendukung tim BD dalam riset pasar, prospek klien, dan penyusunan proposal kemitraan.',
                'responsibilities' => [
                    'Menyusun daftar prospek dan melakukan outreach awal.',
                    'Mendukung pembuatan materi presentasi dan proposal kerja sama.',
                    'Melakukan riset kompetitor dan tren pasar.',
                    'Merapikan CRM dan laporan mingguan.',
                ],
                'qualifications' => [
                    'education' => 'Mahasiswa tingkat akhir atau fresh graduate semua jurusan.',
                    'experience' => 'Pengalaman organisasi/komunitas sebagai nilai tambah.',
                    'skills' => [
                        'Presentasi dan komunikasi bisnis',
                        'Kemampuan riset dan olah data sederhana (Excel/Sheets)',
                        'Dasar negosiasi dan customer focus',
                    ],
                    'other' => ['Bersedia onsite di Semarang', 'Memiliki kendaraan menjadi nilai plus'],
                ],
                'compensation' => 'Uang saku Rp1,5-2 jt/bulan, makan siang, sertifikat magang.',
                'benefits' => ['Kesempatan fast track ke posisi full-time', 'Pelatihan sales dasar', 'Relasi industri FMCG'],
                'apply' => 'Kirim CV ke recruitment@mitrasinergi.co.id, subjek: InternBD_[Nama].',
            ],
            [
                'id' => 'pkl-05',
                'title' => 'PKL Laboratorium Teknik',
                'company' => 'Politeknik Mitra Industri',
                'company_profile' => 'Lembaga vokasi yang membuka PKL di laboratorium manufaktur dan otomasi.',
                'location' => 'Kediri',
                'work_mode' => 'Onsite',
                'job_type' => 'PKL 3 bulan',
                'category' => 'pkl',
                'deadline' => '2025-03-05',
                'summary' => 'Praktik kerja lapangan membantu operasional lab, kalibrasi peralatan, dan asistensi praktikum.',
                'responsibilities' => [
                    'Membantu dosen/instruktur menyiapkan alat praktikum.',
                    'Melakukan pencatatan dan kalibrasi sederhana peralatan lab.',
                    'Mendampingi praktikan selama sesi praktikum.',
                    'Menyusun laporan harian kondisi peralatan.',
                ],
                'qualifications' => [
                    'education' => 'Mahasiswa D3/S1 Teknik Mesin/Elektro semester akhir.',
                    'experience' => 'Pengalaman asisten lab menjadi nilai tambah.',
                    'skills' => [
                        'Dasar K3 laboratorium',
                        'Pemahaman alat ukur mekanik/elektrik',
                        'Disiplin dan teliti',
                    ],
                    'other' => ['Bersedia onsite penuh selama PKL'],
                ],
                'compensation' => 'Uang saku transport dan sertifikat PKL.',
                'benefits' => ['Pendampingan instruktur', 'Akses pelatihan dasar K3', 'Rujukan rekomendasi'],
                'apply' => 'Kirim CV dan surat pengantar kampus ke pkllab@pmi.ac.id dengan subjek: PKL_Lab_[Nama].',
            ],
        ];

        foreach ($jobs as $job) {
            DB::table('job_postings')->updateOrInsert(
                ['title' => $job['title'], 'company' => $job['company']],
                [
                    'company_profile' => $job['company_profile'],
                    'location' => $job['location'],
                    'work_mode' => $job['work_mode'],
                    'job_type' => $job['job_type'],
                    'category' => $job['category'],
                    'deadline' => $job['deadline'],
                    'summary' => $job['summary'],
                    'responsibilities' => json_encode($job['responsibilities']),
                    'qualifications' => json_encode($job['qualifications']),
                    'compensation' => $job['compensation'],
                    'benefits' => json_encode($job['benefits']),
                    'apply' => $job['apply'],
                    'status' => 'published',
                    'published_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }
}
