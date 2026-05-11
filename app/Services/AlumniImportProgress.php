<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class AlumniImportProgress
{
    protected const DIRECTORY = 'imports/progress';

    public static function put(string $importId, array $payload): void
    {
        $body = [
            ...$payload,
            'import_id' => $importId,
            'updated_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put(
            self::path($importId),
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public static function get(string $importId): ?array
    {
        $path = self::path($importId);

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $raw = Storage::disk('local')->get($path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected static function path(string $importId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $importId) ?: $importId;
        return self::DIRECTORY . '/' . $safe . '.json';
    }
}

