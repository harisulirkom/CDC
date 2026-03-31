<?php

namespace App\Http\Controllers;

use App\Http\Resources\CtaResource;
use App\Models\CtaSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CtaController extends Controller
{
    protected array $defaults = [
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

    public function index()
    {
        $this->ensureDefaults();

        $slides = CtaSlide::query()->orderBy('order')->get();

        return CtaResource::collection($slides);
    }

    public function update(Request $request)
    {
        $payload = $request->input('slides', $request->all());
        if (! is_array($payload)) {
            return response()->json(['message' => 'Payload harus berupa array slides'], 422);
        }

        DB::transaction(function () use ($payload) {
            CtaSlide::truncate();
            foreach ($payload as $idx => $slide) {
                CtaSlide::create([
                    'tag' => $slide['tag'] ?? null,
                    'title' => $slide['title'] ?? 'CTA',
                    'highlight' => $slide['highlight'] ?? null,
                    'subtitle' => $slide['subtitle'] ?? null,
                    'chips' => $slide['chips'] ?? [],
                    'primary' => $slide['primary'] ?? null,
                    'secondary' => $slide['secondary'] ?? null,
                    'stats' => $slide['stats'] ?? null,
                    'order' => $slide['order'] ?? $idx,
                ]);
            }
        });

        $slides = CtaSlide::orderBy('order')->get();

        return CtaResource::collection($slides);
    }

    protected function seedDefault()
    {
        foreach ($this->defaults as $idx => $slide) {
            CtaSlide::create(array_merge($slide, ['order' => $slide['order'] ?? $idx]));
        }

        return CtaSlide::orderBy('order')->get();
    }

    /**
     * Pastikan minimal dua CTA default tersedia tanpa menghapus CTA yang sudah ada.
     */
    protected function ensureDefaults(): void
    {
        $existing = CtaSlide::all();
        if ($existing->count() >= 2) {
            return;
        }

        $existingTitles = $existing->pluck('title')->map(fn ($v) => strtolower(trim($v)))->toArray();
        $nextOrder = $existing->max('order') ?? 0;

        foreach ($this->defaults as $default) {
            $key = strtolower(trim($default['title']));
            if (in_array($key, $existingTitles, true)) {
                continue;
            }
            $nextOrder++;
            CtaSlide::create(array_merge($default, ['order' => $default['order'] ?? $nextOrder]));
        }
    }
}
