<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WilayahController extends Controller
{
    private const BASE_URL = 'https://wilayah.id/api';
    private const PROVINCES_CACHE = 'wilayah.provinces';
    private const REGENCIES_CACHE = 'wilayah.regencies.%s';

    public function provinces(): JsonResponse
    {
        $records = Cache::remember(self::PROVINCES_CACHE, 60 * 60 * 12, function () {
            $response = Http::timeout(5)->get(self::BASE_URL . '/provinces.json');
            if (! $response->successful()) {
                return [];
            }
            return $response->json()['data'] ?? [];
        });

        return response()->json(['data' => $records]);
    }

    public function regencies(string $provinceCode): JsonResponse
    {
        $cacheKey = sprintf(self::REGENCIES_CACHE, $provinceCode);
        $records = Cache::remember($cacheKey, 60 * 60 * 12, function () use ($provinceCode) {
            $response = Http::timeout(5)->get(self::BASE_URL . "/regencies/{$provinceCode}.json");
            if (! $response->successful()) {
                return [];
            }
            return $response->json()['data'] ?? [];
        });

        return response()->json(['data' => $records]);
    }
}
