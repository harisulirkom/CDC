<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomepageResource;
use App\Models\HomepageSetting;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    public function show()
    {
        $setting = HomepageSetting::query()->latest()->first();

        if (! $setting) {
            $setting = HomepageSetting::create([
                'data' => [
                    'hero' => [
                        'title' => 'Tracer CDC',
                        'subtitle' => 'Pantau karier alumni secara real-time',
                        'cta_text' => 'Mulai tracer',
                        'cta_link' => '/kuisioner',
                    ],
                    'sections' => [
                        ['title' => 'Dashboard', 'content' => 'Dashboard interaktif untuk pimpinan'],
                        ['title' => 'Integrasi', 'content' => 'Terhubung ke SIAKAD dan portal alumni'],
                    ],
                ],
            ]);
        }

        return new HomepageResource($setting);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hero' => ['nullable', 'array'],
            'sections' => ['nullable', 'array'],
        ]);

        $setting = HomepageSetting::query()->latest()->first();
        if (! $setting) {
            $setting = new HomepageSetting();
        }

        $payload = $setting->data ?? [];
        $setting->data = array_merge($payload, $data);
        $setting->save();

        return new HomepageResource($setting);
    }
}
