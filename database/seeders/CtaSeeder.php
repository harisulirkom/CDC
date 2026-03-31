<?php

namespace Database\Seeders;

use App\Models\CtaSlide;
use Illuminate\Database\Seeder;

class CtaSeeder extends Seeder
{
    public function run(): void
    {
        if (CtaSlide::count() > 0) {
            return;
        }

        $defaults = [
            [
                'tag' => 'CDC UIN Kediri',
                'title' => 'Selamat Datang di Career Development Center',
                'highlight' => 'UIN Syekh Wasil Kediri',
                'subtitle' => 'Wadah Pengembangan Karier, Tracer Study & Kemitraan Industri.',
                'chips' => ['Tracer study', 'Portal CDC', 'Kemitraan industri'],
                'primary' => ['label' => 'Masuk Portal CDC', 'to' => '/layanan'],
                'secondary' => ['label' => 'Lihat layanan ->', 'to' => '/layanan'],
                'stats' => [
                    'labelLeft' => 'Program aktif',
                    'valueLeft' => '12',
                    'labelRight' => 'Mitra industri',
                    'valueRight' => '34',
                    'progress' => 78,
                    'remark' => 'Kolaborasi kampus x industri untuk kesiapan karier.',
                    'badge' => 'Live',
                ],
                'order' => 0,
            ],
            [
                'tag' => 'Tracer Study',
                'title' => 'Isi kuisioner tracer dan dukung akreditasi',
                'highlight' => 'Partisipasi alumni',
                'subtitle' => 'Bantu kampus memetakan outcome karier dan kebutuhan industri.',
                'chips' => ['Tracer alumni', 'Pengguna alumni', 'Dashboard'],
                'primary' => ['label' => 'Isi tracer sekarang', 'to' => '/kuisioner'],
                'secondary' => ['label' => 'Lihat hasil tracer ->', 'to' => '/dashboard'],
                'stats' => [
                    'labelLeft' => 'Response rate',
                    'valueLeft' => '68%',
                    'labelRight' => 'Attempt',
                    'valueRight' => '124',
                    'progress' => 68,
                    'remark' => 'Target respon minimal 70% per prodi.',
                    'badge' => 'Aktif',
                ],
                'order' => 1,
            ],
        ];

        foreach ($defaults as $idx => $slide) {
            CtaSlide::create(array_merge($slide, ['order' => $slide['order'] ?? $idx]));
        }
    }
}
