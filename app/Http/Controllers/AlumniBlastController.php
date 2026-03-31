<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\EmailTemplate;
use App\Services\BrevoService;
use App\Support\EmailTemplateDefaults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AlumniBlastController extends Controller
{
    public function send(Request $request, BrevoService $brevo)
    {
        $request->validate([
            'nims' => ['required', 'array', 'min:1'],
            'nims.*' => ['string'],
            'subject' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'expiry_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'base_url' => ['nullable', 'url'],
        ]);

        if (!$brevo->isConfigured()) {
            return response()->json([
                'message' => 'Brevo belum dikonfigurasi. Lengkapi BREVO_API_KEY dan BREVO_SENDER_EMAIL di backend.',
            ], 503);
        }

        $nims = array_values(array_unique(array_filter($request->input('nims', []))));
        if (!count($nims)) {
            return response()->json(['message' => 'Daftar NIM kosong.'], 422);
        }
        if (count($nims) > 500) {
            return response()->json(['message' => 'Maksimal 500 penerima per request.'], 422);
        }

        $expiryDays = (int) ($request->input('expiry_days') ?: 30);
        $baseUrl = $request->input('base_url') ?: config('app.frontend_url', 'http://localhost:5173');
        $defaults = EmailTemplateDefaults::alumniBlast();
        $storedTemplate = EmailTemplate::query()->where('key', 'alumni-blast')->first();
        $subject = $request->input('subject')
            ?: ($storedTemplate?->subject ?: $defaults['subject']);
        $template = $request->input('message')
            ?: ($storedTemplate?->body ?: $defaults['body']);

        $alumniList = Alumni::query()->whereIn('nim', $nims)->get()->keyBy('nim');

        $sent = [];
        $failed = [];

        foreach ($nims as $nim) {
            $alumni = $alumniList->get($nim);
            if (!$alumni) {
                $failed[] = ['nim' => $nim, 'reason' => 'NIM tidak ditemukan'];
                continue;
            }

            $email = trim((string) $alumni->email);
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed[] = ['nim' => $nim, 'email' => $email, 'reason' => 'Email tidak valid'];
                continue;
            }

            try {
                $link = $this->buildSurveyLink($nim, $expiryDays, $baseUrl);
                $textMessage = $this->renderTemplate($template, [
                    'nama' => $alumni->nama ?? 'Alumni',
                    'nim' => $nim,
                    'prodi' => $alumni->prodi ?? '',
                    'tahun_lulus' => $alumni->tahun_lulus ?? '',
                    'link' => $link,
                ]);
                $htmlMessage = $this->renderHtml($textMessage, $link);

                $payload = $brevo->buildPayload($email, $alumni->nama ?? 'Alumni', $subject, $htmlMessage, $textMessage);
                $result = $brevo->sendEmail($payload);

                if ($result['success'] ?? false) {
                    $alumni->sent = true;
                    $alumni->save();
                    $sent[] = ['nim' => $nim, 'email' => $email];
                } else {
                    $failed[] = [
                        'nim' => $nim,
                        'email' => $email,
                        'reason' => $result['message'] ?? 'Gagal mengirim email',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Blast email failed', [
                    'nim' => $nim,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                $failed[] = [
                    'nim' => $nim,
                    'email' => $email,
                    'reason' => 'Gagal memproses email.',
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total' => count($nims),
                'sent' => count($sent),
                'failed' => count($failed),
            ],
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    private function buildSurveyLink(string $nim, int $expiryDays, string $baseUrl): string
    {
        $expiredAt = Carbon::now()->addDays($expiryDays);
        $payload = [
            'nim' => $nim,
            'exp' => $expiredAt->timestamp,
            'type' => 'survey_access',
        ];

        $token = Crypt::encryptString(json_encode($payload));
        $base = rtrim($baseUrl, '/');

        return $base . '/kuisioner/alumni?token=' . urlencode($token);
    }

    private function renderTemplate(string $template, array $context): string
    {
        $replacements = [
            '{nama}' => $context['nama'] ?? '',
            '{nim}' => $context['nim'] ?? '',
            '{prodi}' => $context['prodi'] ?? '',
            '{tahun_lulus}' => $context['tahun_lulus'] ?? '',
            '{link}' => $context['link'] ?? '',
        ];

        return strtr($template, $replacements);
    }

    private function renderHtml(string $text, string $link): string
    {
        $escaped = nl2br(e($text));
        $linkEscaped = e($link);
        $linkHtml = '<a href="' . e($link) . '">' . $linkEscaped . '</a>';
        $body = str_replace($linkEscaped, $linkHtml, $escaped);

        return '<div style="font-family:Arial, sans-serif; font-size:14px; color:#0f172a;">' . $body . '</div>';
    }
}
