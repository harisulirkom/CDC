<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoService
{
    private string $apiKey;
    private string $senderEmail;
    private string $senderName;
    private ?string $replyToEmail;
    private ?string $replyToName;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.brevo.api_key');
        $this->senderEmail = (string) config('services.brevo.sender_email');
        $this->senderName = (string) (config('services.brevo.sender_name') ?: $this->senderEmail);
        $this->replyToEmail = config('services.brevo.reply_to_email');
        $this->replyToName = config('services.brevo.reply_to_name');
        $this->baseUrl = (string) (config('services.brevo.base_url') ?: 'https://api.brevo.com/v3');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->senderEmail !== '';
    }

    public function buildPayload(string $toEmail, string $toName, string $subject, string $html, string $text): array
    {
        $payload = [
            'sender' => [
                'email' => $this->senderEmail,
                'name' => $this->senderName,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName,
                ],
            ],
            'subject' => $subject,
            'htmlContent' => $html,
            'textContent' => $text,
        ];

        if ($this->replyToEmail) {
            $payload['replyTo'] = [
                'email' => $this->replyToEmail,
                'name' => $this->replyToName ?: $this->replyToEmail,
            ];
        }

        return $payload;
    }

    public function sendEmail(array $payload): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Brevo belum dikonfigurasi.',
            ];
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
        ])->post(rtrim($this->baseUrl, '/') . '/smtp/email', $payload);

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
            ];
        }

        $message = $response->json('message') ?: $response->body();
        return [
            'success' => false,
            'message' => $message ?: 'Brevo request failed.',
            'status' => $response->status(),
        ];
    }
}
