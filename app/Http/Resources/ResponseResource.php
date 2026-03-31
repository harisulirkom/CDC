<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ResponseResource extends JsonResource
{
    protected function resolveAudienceType(): string
    {
        if ($this->alumni_id) {
            return 'alumni';
        }

        $questionnaireAudience = strtolower(trim((string) ($this->questionnaire->audience_normalized ?? $this->questionnaire->audience ?? '')));
        if (str_contains($questionnaireAudience, 'umum')) {
            return 'umum';
        }
        if (str_contains($questionnaireAudience, 'pengguna')) {
            return 'pengguna';
        }
        if (str_contains($questionnaireAudience, 'alumni')) {
            return 'alumni';
        }

        $formAudience = strtolower(trim((string) ($this->form_data['target_audience'] ?? $this->form_data['audience'] ?? '')));
        if (in_array($formAudience, ['alumni', 'umum', 'pengguna'], true)) {
            return $formAudience;
        }

        return 'alumni';
    }

    protected function parseAnswerValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return $value;
    }

    protected function findStatusAnswer(array $answers): ?string
    {
        foreach ($answers as $answer) {
            $questionLabel = strtolower((string) ($answer->question->pertanyaan ?? ''));
            if (str_contains($questionLabel, 'status')) {
                $value = $this->parseAnswerValue($answer->jawaban);
                if (is_array($value)) {
                    $value = reset($value) ?? '';
                }
                return trim(strtolower((string) ($value ?? '')));
            }
        }

        return null;
    }

    public function toArray($request): array
    {
        $alumni = $this->whenLoaded('alumni');

        $dynamicAnswers = [];
        $answers = $this->whenLoaded('answers') ?? [];
        foreach ($answers as $ans) {
            $parsed = $ans->jawaban;
            if (is_string($parsed)) {
                $json = json_decode($parsed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsed = $json;
                }
            }
            $dynamicAnswers[(string) $ans->question_id] = $parsed;
        }
        $answersArray = $answers instanceof \Illuminate\Support\Collection ? $answers->all() : (is_array($answers) ? $answers : []);
        $statusAnswer = $this->findStatusAnswer($answersArray) ?? ($this->form_data['status'] ?? null);

        // Fallback Logic for Anonymous/Pengguna Responses
        $orgName = $this->form_data['organisasi'] ?? $this->form_data['nama'] ?? null;
        $fallbackNim = $this->form_data['nim'] ?? '-';
        $location = $this->form_data['lokasi'] ?? null;
        $contact = $this->form_data['kontak'] ?? $this->form_data['email'] ?? null;
        // Target Alumni Name (for Pengguna who evaluate specific alumni)
        $targetAlumniName = $this->form_data['nama_alumni'] ?? $this->form_data['target_alumni'] ?? null;

        return [
            'id' => $this->id,
            'alumni_id' => $this->alumni_id,
            'alumniId' => $this->alumni_id,
            'questionnaire_id' => $this->questionnaire_id,
            'questionnaireId' => $this->questionnaire_id,
            'attempt_ke' => $this->attempt_ke,
            'attempt_number' => $this->attempt_ke,
            'attemptNumber' => $this->attempt_ke,
            'type' => $this->resolveAudienceType(),
            'status' => $statusAnswer,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),

            // Profil ringkas (Unified)
            'nama' => $alumni?->nama ?? $orgName, // Use Org Name if no Alumni
            'nim' => $alumni?->nim ?? $fallbackNim,
            'prodi' => $alumni?->prodi ?? ($this->form_data['peran'] ?? '-'), // Use Job Role as proxy or dash
            'fakultas' => $alumni?->fakultas ?? ($location ?? '-'), // Use Location as proxy or dash
            'tahun' => $alumni?->tahun_lulus ?? '-',
            'email' => $alumni?->email ?? $contact,
            'no_hp' => $alumni?->no_hp ?? $contact,
            'nik' => $alumni?->nik ?? '-',
            'nama_alumni' => $targetAlumniName, // Expose target alumni name

            // Raw payload
            'raw' => [
                'nama' => $alumni?->nama ?? $orgName,
                'nim' => $alumni?->nim ?? $fallbackNim,
                'prodi' => $alumni?->prodi ?? null,
                'fakultas' => $alumni?->fakultas ?? null,
                'tahun' => $alumni?->tahun_lulus ?? null,
                'email' => $alumni?->email ?? null,
                'noHp' => $alumni?->no_hp ?? null,
                'nik' => $alumni?->nik ?? null,
                'dynamicAnswers' => (object) $dynamicAnswers,
                'formData' => $this->form_data ?? [],
                'extra' => $this->form_data['extra'] ?? [],
                'attemptNumber' => $this->attempt_ke,
                'timestamp' => $this->created_at?->toIso8601String(),
            ],
            'questionnaire' => $this->whenLoaded('questionnaire', function () {
                return [
                    'id' => $this->questionnaire->id,
                    'judul' => $this->questionnaire->judul,
                    'title' => $this->questionnaire->judul,
                    'deskripsi' => $this->questionnaire->deskripsi,
                    'description' => $this->questionnaire->deskripsi,
                    'status' => $this->questionnaire->status,
                ];
            }),
            'answers' => ResponseAnswerResource::collection(
                $this->whenLoaded('answers')
            ),
        ];
    }
}
