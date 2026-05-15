<?php

namespace App\Http\Controllers;

use App\Models\CareerAdvisorSession;
use App\Services\CareerAdvisorAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CareerAdvisorController extends Controller
{
    private const PERSONAS = [
        ['id' => 'fresh', 'label' => 'Fresh Graduate', 'summary' => 'Belum bekerja, butuh peta jalan karir yang jelas.'],
        ['id' => 'switcher', 'label' => 'Career Switcher', 'summary' => 'Ingin pindah jalur kerja dengan risiko transisi lebih rendah.'],
        ['id' => 'entrepreneur', 'label' => 'Entrepreneur Track', 'summary' => 'Menjalankan usaha sambil menjaga peluang karir profesional.'],
    ];

    private const INDUSTRIES = ['Teknologi', 'Pendidikan', 'Perbankan', 'Pemerintahan', 'Kreatif', 'Kesehatan'];
    private const SKILL_LEVELS = ['dasar', 'menengah', 'lanjut'];
    private const WORK_STYLES = ['Remote', 'Hybrid', 'Onsite'];
    private const WEEKLY_HOURS = ['3-5', '6-8', '>8'];
    private const MOTIVATORS = ['Stabilitas karir', 'Dampak sosial', 'Penghasilan', 'Fleksibilitas waktu', 'Pembelajaran cepat'];
    private const SUPPORT_TYPES = ['Konseling CDC', 'Roadmap AI mandiri', 'Mentoring alumni', 'Komunitas praktik'];

    private const REQUIRED_FIELDS = [
        'graduation_year',
        'study_program',
        'target_industry',
        'target_role',
        'skill_level',
        'strongest_skill',
        'biggest_gap',
        'work_style',
        'weekly_hours',
        'motivator',
        'career_goal',
        'support_type',
    ];

    public function options()
    {
        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => [
                'personas' => self::PERSONAS,
                'industries' => self::INDUSTRIES,
                'skill_levels' => self::SKILL_LEVELS,
                'work_styles' => self::WORK_STYLES,
                'weekly_hours' => self::WEEKLY_HOURS,
                'motivators' => self::MOTIVATORS,
                'support_types' => self::SUPPORT_TYPES,
                'source' => 'api',
            ],
        ]);
    }

    public function createSession(Request $request)
    {
        $validated = $request->validate([
            'persona_id' => ['required', 'string', 'in:fresh,switcher,entrepreneur'],
        ]);

        $session = CareerAdvisorSession::create([
            'user_id' => $request->user()->id,
            'session_id' => 'ca_sess_'.Str::lower((string) Str::ulid()),
            'persona_id' => $validated['persona_id'],
            'profile_data' => [],
            'form_completion_percent' => 0,
            'confidence_band' => 'rendah',
            'ready_for_generate' => false,
            'generation_status' => 'idle',
            'recommendation_source' => 'api',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Session created',
            'data' => [
                'session_id' => $session->session_id,
                'persona_id' => $session->persona_id,
                'form_completion_percent' => 0,
                'ready_for_generate' => false,
                'created_at' => optional($session->created_at)->toIso8601String(),
                'source' => 'api',
            ],
        ], 201);
    }

    public function updateProfile(Request $request, CareerAdvisorSession $session)
    {
        $this->authorizeSession($request, $session);

        $validated = $request->validate([
            'graduation_year' => ['nullable', 'integer', 'digits:4', 'min:1990', 'max:2100'],
            'study_program' => ['nullable', 'string', 'max:120'],
            'target_industry' => ['nullable', 'string', 'max:80'],
            'target_role' => ['nullable', 'string', 'max:120'],
            'skill_level' => ['nullable', 'string', 'in:dasar,menengah,lanjut'],
            'strongest_skill' => ['nullable', 'string', 'max:120'],
            'biggest_gap' => ['nullable', 'string', 'max:120'],
            'work_style' => ['nullable', 'string', 'max:40'],
            'location_preference' => ['nullable', 'string', 'max:120'],
            'weekly_hours' => ['nullable', 'string', 'max:20'],
            'motivator' => ['nullable', 'string', 'max:120'],
            'career_goal' => ['nullable', 'string', 'max:255'],
            'main_constraint' => ['nullable', 'string', 'max:255'],
            'support_type' => ['nullable', 'string', 'max:120'],
        ]);

        $profile = array_merge($session->profile_data ?? [], $validated);

        $missingRequiredFields = collect(self::REQUIRED_FIELDS)
            ->filter(fn (string $field) => trim((string) ($profile[$field] ?? '')) === '')
            ->values()
            ->all();

        $completionPercent = (int) round(((count(self::REQUIRED_FIELDS) - count($missingRequiredFields)) / count(self::REQUIRED_FIELDS)) * 100);

        $session->update([
            'profile_data' => $profile,
            'form_completion_percent' => $completionPercent,
            'confidence_band' => $this->confidenceBand($completionPercent),
            'ready_for_generate' => count($missingRequiredFields) === 0,
            'generation_status' => 'idle',
            'analysis_id' => null,
            'recommendation_data' => null,
            'generated_at' => null,
            'recommendation_source' => 'api',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated',
            'data' => [
                'session_id' => $session->session_id,
                'form_completion_percent' => $completionPercent,
                'confidence_band' => $this->confidenceBand($completionPercent),
                'ready_for_generate' => count($missingRequiredFields) === 0,
                'missing_required_fields' => $missingRequiredFields,
                'source' => 'api',
            ],
        ]);
    }

    public function generate(Request $request, CareerAdvisorSession $session, CareerAdvisorAiService $aiService)
    {
        $this->authorizeSession($request, $session);

        if (! $session->ready_for_generate) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'error_code' => 'PROFILE_INCOMPLETE',
                'errors' => $this->missingFieldErrors($session->profile_data ?? []),
                'request_id' => 'req_'.Str::lower((string) Str::ulid()),
            ], 422);
        }

        if (! $aiService->isConfigured()) {
            return response()->json([
                'status' => false,
                'message' => 'OpenAI belum dikonfigurasi di backend.',
                'error_code' => 'AI_NOT_CONFIGURED',
                'request_id' => 'req_'.Str::lower((string) Str::ulid()),
            ], 503);
        }

        $session->generation_status = 'in_progress';
        $session->save();

        try {
            $analysisId = 'ca_an_'.Str::lower((string) Str::ulid());
            $fallback = $this->buildFallbackRecommendation($session);

            $aiOutput = $aiService->generate([
                'persona_id' => $session->persona_id,
                'profile' => $session->profile_data ?? [],
            ]);

            $normalized = $this->normalizeAiOutput($aiOutput, $fallback);

            $session->update([
                'analysis_id' => $analysisId,
                'generation_status' => 'completed',
                'recommendation_data' => $normalized,
                'recommendation_source' => 'openai',
                'generated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Generation completed',
                'data' => [
                    'session_id' => $session->session_id,
                    'analysis_id' => $analysisId,
                    'generation_status' => 'completed',
                    'source' => 'openai',
                ],
            ]);
        } catch (\Throwable $e) {
            $session->generation_status = 'failed';
            $session->save();

            Log::warning('Career advisor generation failed', [
                'session_id' => $session->session_id,
                'user_id' => $session->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Gagal memproses rekomendasi AI.',
                'error_code' => 'GENERATION_FAILED',
                'request_id' => 'req_'.Str::lower((string) Str::ulid()),
            ], 500);
        }
    }

    public function result(Request $request, CareerAdvisorSession $session)
    {
        $this->authorizeSession($request, $session);

        if ($session->generation_status !== 'completed' || ! is_array($session->recommendation_data)) {
            return response()->json([
                'status' => true,
                'message' => 'Generation in progress',
                'data' => [
                    'session_id' => $session->session_id,
                    'generation_status' => $session->generation_status,
                    'source' => 'api',
                ],
            ], 202);
        }

        $recommendation = $session->recommendation_data;

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => [
                'session_id' => $session->session_id,
                'analysis_id' => $session->analysis_id,
                'generation_status' => 'completed',
                'confidence_score' => (float) ($recommendation['confidence_score'] ?? 0.75),
                'confidence_band' => (string) ($recommendation['confidence_band'] ?? $session->confidence_band),
                'motivation_narrative' => (string) ($recommendation['motivation_narrative'] ?? ''),
                'recommendations' => $recommendation['recommendations'] ?? [],
                'skill_gap' => $recommendation['skill_gap'] ?? [],
                'plan_12_weeks' => $recommendation['plan_12_weeks'] ?? [],
                'generated_at' => optional($session->generated_at)->toIso8601String(),
                'source' => (string) ($session->recommendation_source ?: 'api'),
            ],
        ]);
    }

    public function saveAction(Request $request, CareerAdvisorSession $session)
    {
        $this->authorizeSession($request, $session);

        $validated = $request->validate([
            'next_action' => ['required', 'string', 'in:apply_now,book_counseling,save_learning_plan'],
        ]);

        $session->update([
            'next_action' => $validated['next_action'],
            'action_saved_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Action saved',
            'data' => [
                'session_id' => $session->session_id,
                'next_action' => $session->next_action,
                'saved_at' => optional($session->action_saved_at)->toIso8601String(),
                'source' => 'api',
            ],
        ]);
    }

    public function saveFeedback(Request $request, CareerAdvisorSession $session)
    {
        $this->authorizeSession($request, $session);

        $validated = $request->validate([
            'relevance_score' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_note' => ['nullable', 'string', 'max:500'],
        ]);

        $session->update([
            'relevance_score' => $validated['relevance_score'],
            'feedback_note' => $validated['feedback_note'] ?? null,
            'feedback_saved_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Feedback saved',
            'data' => [
                'session_id' => $session->session_id,
                'relevance_score' => $session->relevance_score,
                'saved_at' => optional($session->feedback_saved_at)->toIso8601String(),
                'source' => 'api',
            ],
        ]);
    }

    private function authorizeSession(Request $request, CareerAdvisorSession $session): void
    {
        if ((int) $session->user_id !== (int) $request->user()->id) {
            abort(403, 'Forbidden');
        }
    }

    private function confidenceBand(int $completionPercent): string
    {
        if ($completionPercent < 60) {
            return 'rendah';
        }

        if ($completionPercent < 85) {
            return 'sedang';
        }

        return 'tinggi';
    }

    private function missingFieldErrors(array $profile): array
    {
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (trim((string) ($profile[$field] ?? '')) === '') {
                $errors[$field] = ["{$field} is required"];
            }
        }

        return $errors;
    }

    private function buildFallbackRecommendation(CareerAdvisorSession $session): array
    {
        $profile = $session->profile_data ?? [];
        $targetRole = (string) ($profile['target_role'] ?? 'Target role');
        $targetIndustry = (string) ($profile['target_industry'] ?? 'Industri pilihan');
        $motivator = (string) ($profile['motivator'] ?? 'motivasi karir');
        $strongestSkill = (string) ($profile['strongest_skill'] ?? 'keterampilan utama');
        $biggestGap = (string) ($profile['biggest_gap'] ?? 'keterampilan prioritas');
        $weeklyHours = (string) ($profile['weekly_hours'] ?? '6-8');

        return [
            'confidence_score' => min(0.95, max(0.58, ((int) $session->form_completion_percent / 100) + 0.05)),
            'confidence_band' => $session->confidence_band,
            'motivation_narrative' => "Kamu menargetkan {$targetRole} di sektor {$targetIndustry} dengan motivator utama {$motivator}. Kekuatanmu di {$strongestSkill} dan gap utama di {$biggestGap}. Dengan komitmen {$weeklyHours} jam/minggu, rencana ini dirancang agar progresmu terukur dan realistis.",
            'recommendations' => [
                [
                    'role' => $targetRole,
                    'score' => 84,
                    'eta' => '8-10 minggu',
                    'reason' => "Cocok dengan target industri {$targetIndustry} dan fokus pengembanganmu saat ini.",
                ],
                [
                    'role' => 'Business Intelligence Associate',
                    'score' => 79,
                    'eta' => '10-12 minggu',
                    'reason' => 'Masih dekat dengan lintasan analis data dan dapat dicapai melalui project portofolio.',
                ],
                [
                    'role' => 'Operations Analyst',
                    'score' => 73,
                    'eta' => '12 minggu',
                    'reason' => 'Memberi opsi lintas fungsi untuk memperluas peluang awal karir.',
                ],
            ],
            'skill_gap' => [
                $biggestGap,
                'Portfolio project end-to-end',
                'Interview readiness',
            ],
            'plan_12_weeks' => [
                ['phase' => 'Minggu 1-2', 'focus' => 'Fundamental skill dan baseline assessment'],
                ['phase' => 'Minggu 3-4', 'focus' => 'Mini project pertama dan review mentor'],
                ['phase' => 'Minggu 5-8', 'focus' => 'Project portofolio berdasarkan industri target'],
                ['phase' => 'Minggu 9-12', 'focus' => 'Mock interview, CV refinement, apply plan'],
            ],
        ];
    }

    private function normalizeAiOutput(array $aiOutput, array $fallback): array
    {
        $recommendations = collect($aiOutput['recommendations'] ?? [])
            ->map(function ($item) {
                return [
                    'role' => trim((string) ($item['role'] ?? '')),
                    'score' => max(0, min(95, (int) ($item['score'] ?? 0))),
                    'eta' => trim((string) ($item['eta'] ?? '')),
                    'reason' => trim((string) ($item['reason'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['role'] !== '' && $item['eta'] !== '' && $item['reason'] !== '' && $item['score'] > 0)
            ->values()
            ->all();

        $plan = collect($aiOutput['plan_12_weeks'] ?? [])
            ->map(function ($item) {
                return [
                    'phase' => trim((string) ($item['phase'] ?? '')),
                    'focus' => trim((string) ($item['focus'] ?? '')),
                ];
            })
            ->filter(fn ($item) => $item['phase'] !== '' && $item['focus'] !== '')
            ->values()
            ->all();

        $skillGap = collect($aiOutput['skill_gap'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        $mergedRecommendations = $recommendations;
        if (count($mergedRecommendations) < 3) {
            foreach ($fallback['recommendations'] as $fallbackItem) {
                $exists = collect($mergedRecommendations)
                    ->contains(fn ($item) => strcasecmp((string) $item['role'], (string) $fallbackItem['role']) === 0);

                if (! $exists) {
                    $mergedRecommendations[] = $fallbackItem;
                }

                if (count($mergedRecommendations) >= 3) {
                    break;
                }
            }
        }

        $mergedPlan = $plan;
        if (count($mergedPlan) < 4) {
            foreach ($fallback['plan_12_weeks'] as $fallbackItem) {
                $mergedPlan[] = $fallbackItem;
                if (count($mergedPlan) >= 4) {
                    break;
                }
            }
        }

        return [
            'confidence_score' => max(0.0, min(0.99, (float) ($aiOutput['confidence_score'] ?? $fallback['confidence_score']))),
            'confidence_band' => (string) ($aiOutput['confidence_band'] ?? $fallback['confidence_band']),
            'motivation_narrative' => trim((string) ($aiOutput['motivation_narrative'] ?? $fallback['motivation_narrative'])),
            'recommendations' => array_slice($mergedRecommendations, 0, 5),
            'skill_gap' => count($skillGap) ? array_slice($skillGap, 0, 8) : $fallback['skill_gap'],
            'plan_12_weeks' => array_slice($mergedPlan, 0, 6),
        ];
    }
}
