<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class AlumniImportReport
{
    protected const DIRECTORY = 'imports/reports';

    public static function storeFailedRows(string $importId, array $rows): ?string
    {
        if (empty($rows)) {
            return null;
        }

        $path = self::path($importId);
        $stream = fopen('php://temp', 'r+');
        if (!$stream) {
            return null;
        }

        try {
            fputcsv($stream, ['row_number', 'nim', 'nama', 'email', 'reason']);
            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['row_number'] ?? '',
                    $row['nim'] ?? '',
                    $row['nama'] ?? '',
                    $row['email'] ?? '',
                    $row['reason'] ?? '',
                ]);
            }

            rewind($stream);
            $csv = stream_get_contents($stream);
            if (!is_string($csv)) {
                return null;
            }

            Storage::disk('local')->put($path, $csv);
            return $path;
        } finally {
            fclose($stream);
        }
    }

    public static function path(string $importId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $importId) ?: $importId;
        return self::DIRECTORY . '/' . $safe . '-failed.csv';
    }
}

