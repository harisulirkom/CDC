<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlumniHubResource;
use App\Models\AlumniHubSetting;
use Illuminate\Http\Request;

class AlumniHubController extends Controller
{
    private const SETTING_KEY = 'alumni_hub';

    public function show()
    {
        return new AlumniHubResource($this->setting());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'array'],
        ]);

        $setting = $this->setting();
        $setting->content = $this->normalizeContent($data['content']);
        $setting->updated_by = $request->user()?->id;
        $setting->save();

        return new AlumniHubResource($setting->refresh());
    }

    protected function setting(): AlumniHubSetting
    {
        $setting = AlumniHubSetting::query()->firstOrCreate(
            ['key' => self::SETTING_KEY],
            ['content' => self::defaultContent()],
        );

        $normalized = $this->normalizeContent($setting->content ?? []);
        if ($setting->content !== $normalized) {
            $setting->content = $normalized;
            $setting->save();
        }

        return $setting;
    }

    protected function normalizeContent(array $content): array
    {
        $normalized = array_replace_recursive(self::defaultContent(), $content);

        if (array_key_exists('features', $content) && is_array($content['features'])) {
            $normalized['features'] = array_values($content['features']);
        }

        if (isset($content['gallery']) && is_array($content['gallery']) && array_key_exists('items', $content['gallery'])) {
            $normalized['gallery']['items'] = is_array($content['gallery']['items'])
                ? array_values($content['gallery']['items'])
                : [];
        }

        if (isset($content['agenda']) && is_array($content['agenda']) && array_key_exists('items', $content['agenda'])) {
            $normalized['agenda']['items'] = is_array($content['agenda']['items'])
                ? array_values($content['agenda']['items'])
                : [];
        }

        return $normalized;
    }

    public static function defaultContent(): array
    {
        return [
            'hero' => [
                'badge' => 'Pojok Alumni',
                'headlinePrefix' => 'Bersama',
                'headlineHighlight' => 'Alumni',
                'headlineSuffix' => 'Membangun Masa Depan yang Lebih Baik',
                'description' => 'Alumni Community Hub menjadi ruang digital untuk mempertemukan lulusan, kampus, dan mitra dalam ekosistem kolaborasi yang hangat, produktif, dan berdampak bagi generasi berikutnya.',
                'imageUrl' => 'https://images.unsplash.com/photo-1543269865-cbf427effbad?auto=format&fit=crop&w=1200&q=80',
                'testimonial' => 'Alumni hebat adalah inspirasi bagi generasi selanjutnya.',
                'testimonialLabel' => 'Alumni Community Hub',
            ],
            'features' => [
                [
                    'title' => 'Terhubung & Berkolaborasi',
                    'description' => 'Bangun jejaring lintas angkatan, prodi, dan profesi.',
                    'icon' => 'network',
                ],
                [
                    'title' => 'Berbagi Pengalaman',
                    'description' => 'Ruang cerita karier, studi lanjut, dan pembelajaran alumni.',
                    'icon' => 'story',
                ],
                [
                    'title' => 'Memberi Dampak',
                    'description' => 'Kolaborasi kontribusi untuk mahasiswa dan kampus.',
                    'icon' => 'impact',
                ],
            ],
            'gallery' => [
                'eyebrow' => 'Kegiatan Alumni',
                'title' => 'Galeri Kegiatan Alumni',
                'buttonLabel' => 'Lihat Semua Galeri',
                'buttonUrl' => '/coming-soon/galeri-alumni',
                'items' => [
                    [
                        'title' => 'Seminar alumni lintas profesi',
                        'label' => '16 Mei 2026',
                        'description' => 'Dokumentasi kegiatan alumni dan kampus.',
                        'imageUrl' => 'https://images.unsplash.com/photo-1515187029135-18ee286d815b?auto=format&fit=crop&w=1200&q=80',
                    ],
                    [
                        'title' => 'Sharing session karier',
                        'imageUrl' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Foto bersama alumni',
                        'imageUrl' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Diskusi komunitas',
                        'imageUrl' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Workshop alumni',
                        'moreLabel' => '+12 Foto Lainnya',
                        'description' => 'Buka arsip dokumentasi alumni',
                        'imageUrl' => 'https://images.unsplash.com/photo-1558403194-611308249627?auto=format&fit=crop&w=900&q=80',
                    ],
                ],
            ],
            'agenda' => [
                'eyebrow' => 'Agenda Komunitas',
                'title' => 'Agenda & Acara Alumni',
                'subtitle' => 'Ikuti agenda terbaru untuk memperluas jejaring, mengembangkan diri, dan berkontribusi untuk kampus.',
                'buttonLabel' => 'Lihat Semua Acara',
                'buttonUrl' => '/coming-soon/acara-alumni',
                'items' => [
                    [
                        'title' => 'Temu Alumni UIN Syekh Wasil Kediri',
                        'date' => '16 Mei',
                        'time' => '08.00 WIB - Selesai',
                        'place' => 'Auditorium Lantai 4 Perpustakaan',
                        'tag' => 'Networking',
                        'color' => 'from-sky-500 to-cyan-400',
                        'imageUrl' => 'https://images.unsplash.com/photo-1540317580384-e5d43867caa6?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Career Sharing: Dari Kampus ke Industri',
                        'date' => '24 Mei',
                        'time' => '09.00 - 11.30 WIB',
                        'place' => 'Ruang Kolaborasi CDC',
                        'tag' => 'Sharing Session',
                        'color' => 'from-indigo-500 to-blue-400',
                        'imageUrl' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Workshop Personal Branding Alumni',
                        'date' => '31 Mei',
                        'time' => '13.00 - 15.30 WIB',
                        'place' => 'Hybrid Zoom & Aula Kampus',
                        'tag' => 'Pengembangan Diri',
                        'color' => 'from-emerald-500 to-teal-400',
                        'imageUrl' => 'https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=900&q=80',
                    ],
                    [
                        'title' => 'Gerakan Alumni Mengabdi',
                        'date' => '08 Jun',
                        'time' => '07.30 - 12.00 WIB',
                        'place' => 'Kediri Raya',
                        'tag' => 'Pengabdian Masyarakat',
                        'color' => 'from-cyan-500 to-blue-500',
                        'imageUrl' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&w=900&q=80',
                    ],
                ],
            ],
            'cta' => [
                'title' => 'Jadilah bagian dari komunitas alumni UIN Syekh Wasil Kediri',
                'description' => 'Perbarui data diri Anda dan dapatkan informasi terbaru seputar kegiatan alumni.',
                'buttonLabel' => 'Perbarui Data Alumni',
                'buttonUrl' => '/kuisioner/alumni',
            ],
        ];
    }
}
