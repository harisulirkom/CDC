<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\Response;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SurveyTokenController extends Controller
{
    /**
     * Generate a secure encrypted token for an Alumni.
     * Accessible only by Admins.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'nim' => 'required|string|max:32|exists:alumnis,nim',
            'expiry_days' => 'nullable|integer|min:1|max:90',
            'base_url' => 'nullable|url|max:255',
        ]);

        $nim = $request->input('nim');
        $days = $request->input('expiry_days', 30);
        $expiredAt = Carbon::now()->addDays($days);
        $alumni = Alumni::where('nim', $nim)->first();

        // Payload to be encrypted
        $payload = [
            'nim' => $nim,
            'exp' => $expiredAt->timestamp,
            'type' => 'survey_access',
        ];

        try {
            $token = Crypt::encryptString(json_encode($payload));

            AuditLogger::log('survey_token.generated', 'alumni', $alumni?->id, [
                'expires_at' => $expiredAt->toIso8601String(),
            ]);

            return response()->json([
                'token' => $token,
                'expires_at' => $expiredAt->toIso8601String(),
                'url' => $request->input('base_url', config('app.frontend_url', 'http://localhost:5173')) . '/kuisioner/alumni?token=' . urlencode($token),
            ]);
        } catch (\Exception $e) {
            Log::error('Token generation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat token'], 500);
        }
    }

    /**
     * Validate a token and return the Alumni details if valid.
     * Public endpoint (used by Alumni when clicking the link).
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $decrypted = Crypt::decryptString($request->input('token'));
            $payload = json_decode($decrypted, true);

            if (!isset($payload['nim']) || !isset($payload['exp'])) {
                return response()->json(['message' => 'Token tidak valid (Format salah)'], 401);
            }

            if (Carbon::now()->timestamp > $payload['exp']) {
                return response()->json(['message' => 'Token sudah kedaluwarsa'], 401);
            }

            $alumni = Alumni::where('nim', $payload['nim'])->firstOrFail();
            $alumniPayload = (new \App\Http\Resources\PublicAlumniResource($alumni))->toArray($request);
            $alumniPayload = $this->mergeResponseFallback($alumniPayload, $alumni->id);

            return response()->json([
                'valid' => true,
                'alumni' => $alumniPayload,
            ]);

        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json(['message' => 'Token tidak valid atau rusak'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan saat validasi token'], 500);
        }
    }

    protected function mergeResponseFallback(array $payload, int $alumniId): array
    {
        $latest = Response::query()
            ->where('alumni_id', $alumniId)
            ->latest()
            ->first();
        if (!$latest) {
            return $payload;
        }

        $formData = $latest->form_data ?? [];
        if (!is_array($formData)) {
            return $payload;
        }

        $nik = $payload['nik'] ?? null;
        if (!$nik) {
            $nik = $this->pickFirst($formData, ['nik', 'no_ktp', 'ktp', 'nik_alumni']);
        }

        $alamat = $payload['alamat'] ?? null;
        if (!$alamat) {
            $alamat = $this->pickFirst($formData, [
                'alamat',
                'alamat_lengkap',
                'alamat_rumah',
                'alamat_domisili',
                'alamatDomisili',
                'alamat_mahasiswa',
                'alamat_alumni',
            ]);
        }

        $noHp = $payload['no_hp'] ?? null;
        if (!$noHp) {
            $noHp = $this->pickFirst($formData, [
                'no_hp',
                'hp',
                'phone',
                'nomor_hp',
                'noHp',
                'hp_alumni',
            ]);
        }

        if ($nik) {
            $payload['nik'] = $nik;
            $payload['nik_alumni'] = $payload['nik_alumni'] ?? $nik;
            $payload['no_ktp'] = $payload['no_ktp'] ?? $nik;
            $payload['ktp'] = $payload['ktp'] ?? $nik;
        }

        if ($alamat) {
            $payload['alamat'] = $alamat;
            $payload['alamat_alumni'] = $payload['alamat_alumni'] ?? $alamat;
            $payload['alamat_mahasiswa'] = $payload['alamat_mahasiswa'] ?? $alamat;
            $payload['alamat_lengkap'] = $payload['alamat_lengkap'] ?? $alamat;
            $payload['alamat_rumah'] = $payload['alamat_rumah'] ?? $alamat;
        }

        if ($noHp) {
            $payload['no_hp'] = $noHp;
            $payload['hp'] = $payload['hp'] ?? $noHp;
            $payload['noHp'] = $payload['noHp'] ?? $noHp;
            $payload['nomor_hp'] = $payload['nomor_hp'] ?? $noHp;
            $payload['phone'] = $payload['phone'] ?? $noHp;
        }

        return $payload;
    }

    protected function pickFirst(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return (string) $source[$key];
            }
        }
        return null;
    }
}
