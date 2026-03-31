<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $news = [
            [
                'title' => 'Tracer Study 2025 resmi diluncurkan',
                'summary' => 'Pengisian kuisioner tracer study 2025 dibuka untuk seluruh alumni lintas angkatan.',
                'content' => 'Kampus membuka tracer study 2025 guna memetakan outcome karier alumni. Partisipasi alumni akan membantu pengembangan kurikulum, akreditasi, dan pemetaan kebutuhan industri. Silakan isi kuisioner melalui portal tracer.',
                'image_url' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=800&q=60',
            ],
            [
                'title' => 'Career Fair & Campus Hiring 2025',
                'summary' => 'Lebih dari 30 perusahaan mitra membuka booth rekrutmen di aula kampus minggu depan.',
                'content' => 'CDC menggandeng 30+ perusahaan nasional untuk career fair. Alumni dapat membawa CV terbaru, mengikuti sesi coaching singkat, dan melamar langsung di booth. Tersedia jalur magang hingga full-time.',
                'image_url' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=800&q=60',
            ],
            [
                'title' => 'Bootcamp Softskill Digital Batch 2',
                'summary' => 'Program intensif 2 minggu untuk public speaking, presentasi, dan branding profesional.',
                'content' => 'CDC membuka pendaftaran Bootcamp Softskill Digital Batch 2. Peserta akan belajar storytelling, presentasi efektif, dan membangun personal brand di LinkedIn. Kuota terbatas, prioritas untuk alumni 2020-2024.',
                'image_url' => 'https://images.unsplash.com/photo-1529333166433-6761d1b9c83d?auto=format&fit=crop&w=800&q=60',
            ],
            [
                'title' => 'Rilis dashboard baru untuk pelacakan alumni',
                'summary' => 'Dashboard tracer terbaru menampilkan visualisasi serapan kerja, gaji awal, dan sebaran wilayah.',
                'content' => 'Tim CDC merilis dashboard interaktif untuk memonitor hasil tracer study secara real-time. Data agregat menampilkan tren serapan kerja, gaji awal, hingga peta sebaran alumni per provinsi.',
                'image_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=60',
            ],
            // Artikel (disimpan di tabel yang sama)
            [
                'title' => 'Tips membuat CV',
                'summary' => 'Struktur CV singkat, poin prestasi, dan contoh kata kerja aktif agar lolos screening ATS.',
                'content' => 'Gunakan struktur singkat: ringkasan profil, pengalaman relevan, pendidikan, dan skill. Sorot prestasi terukur (contoh: meningkatkan efisiensi 20%). Pakai kata kerja aktif seperti "mengelola", "mengoptimalkan", "merancang". Sesuaikan dengan deskripsi lowongan. Tambahkan tautan portofolio bila ada.',
                'image_url' => 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=800&q=60',
            ],
            [
                'title' => 'Persiapan interview',
                'summary' => 'Checklist riset perusahaan, jawaban STAR, dan etika komunikasi di sesi daring maupun onsite.',
                'content' => 'Siapkan riset perusahaan (produk, model bisnis, kompetitor). Latih jawaban STAR untuk pengalaman kerja. Pastikan koneksi/studio online rapi. Siapkan pertanyaan balik tentang peran. Jaga kontak mata, bahasa tubuh, dan follow-up email singkat setelah wawancara.',
                'image_url' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'title' => 'Etika kerja',
                'summary' => 'Kebiasaan profesional, manajemen waktu, dan komunikasi efektif di tempat kerja.',
                'content' => 'Datang tepat waktu, komunikasikan progres secara proaktif, dan gunakan kanal resmi. Dokumentasikan keputusan, hormati jadwal rapat, dan kelola prioritas dengan to-do list. Jaga etika digital: pesan singkat, sopan, dan jelas.',
                'image_url' => 'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=800&q=80',
            ],
            [
                'title' => 'Analisis pasar kerja',
                'summary' => 'Cara membaca tren industri, gaji, dan skill yang paling dicari untuk memetakan peluang.',
                'content' => 'Gunakan sumber data seperti laporan gaji, portal lowongan, dan indeks keterampilan. Catat peran yang naik daun dan sertifikasi yang dibutuhkan. Bandingkan rentang gaji per lokasi dan skema kerja (remote/hybrid). Susun rencana upskilling berdasar gap skill.',
                'image_url' => 'https://images.unsplash.com/photo-1454165205744-3b78555e5572?auto=format&fit=crop&w=800&q=80',
            ],
        ];

        foreach ($news as $item) {
            DB::table('news')->updateOrInsert(
                ['title' => $item['title']],
                [
                    'summary' => $item['summary'],
                    'content' => $item['content'],
                    'image_url' => $item['image_url'] ?? null,
                    'published' => true,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
