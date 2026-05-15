<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CareerAdvisorAiService
{
    public function isConfigured(): bool
    {
        return (string) config('services.openai.api_key', '') !== '';
    }

    public function generate(array $payload): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.model', 'gpt-4.1-mini');
        $timeout = max(10, (int) config('services.openai.timeout', 25));
        $verifySsl = $this->parseBool(config('services.openai.verify_ssl', true));

        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI belum dikonfigurasi. Isi OPENAI_API_KEY di backend.');
        }

        $systemPrompt = implode("\n", [
            'Kamu adalah AI career advisor untuk alumni perguruan tinggi di Indonesia.',
            'Kembalikan JSON valid dengan key berikut:',
            '{',
            '  "motivation_narrative": "string",',
            '  "recommendations": [{"role":"string","score":number,"eta":"string","reason":"string"}],',
            '  "skill_gap": ["string"],',
            '  "plan_12_weeks": [{"phase":"string","focus":"string"}]',
            '}',
            'Aturan:',
            '- Bahasa Indonesia formal namun ringkas dan memotivasi.',
            '- recommendations minimal 3 item.',
            '- plan_12_weeks minimal 4 item.',
            '- score wajib integer 0-95.',
            '- Jangan menambahkan key lain.',
        ]);

        $response = Http::withOptions(['verify' => $verifySsl])
            ->timeout($timeout)
            ->acceptJson()
            ->withToken($apiKey)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'temperature' => 0.35,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ],
            ]);

        if (! $response->successful()) {
            $errorMessage = $response->json('error.message')
                ?: $response->json('message')
                ?: $response->body();

            throw new \RuntimeException('OpenAI request gagal: '.(string) $errorMessage);
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $decoded = $this->decodeJsonPayload($content);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Format respons OpenAI tidak valid.');
        }

        return $decoded;
    }

    private function decodeJsonPayload(string $content): ?array
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```json\s*(.*?)```/is', $trimmed, $matches)) {
            $decodedFence = json_decode(trim($matches[1]), true);
            if (is_array($decodedFence)) {
                return $decodedFence;
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decodedSlice = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if (is_array($decodedSlice)) {
                return $decodedSlice;
            }
        }

        return null;
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return true;
        }

        return ! in_array($normalized, ['0', 'false', 'off', 'no'], true);
    }
}
