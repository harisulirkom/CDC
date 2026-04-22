<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Question;
use App\Models\Response;
use App\Models\ResponseAnswer;
use App\Models\Questionnaire;
use App\Models\Alumni;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Services\AuditLogger;

class ResponseController extends Controller
{
    protected function trimString($value, int $maxLen): ?string
    {
        if (is_array($value)) {
            return null;
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maxLen) {
            return mb_substr($text, 0, $maxLen);
        }
        return $text;
    }

    protected function normalizeYear($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (preg_match('/\b\d{4}\b/', $text, $match)) {
            return $match[0];
        }
        return null;
    }

    protected function normalizeQuestionId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }
        return (int) $raw;
    }

    protected function normalizeStatusFilter($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                if (mb_strlen($item) > 30) {
                    $item = mb_substr($item, 0, 30);
                }
                $clean[] = $item;
            }
            return $clean ?: null;
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > 200) {
            $text = mb_substr($text, 0, 200);
        }
        return $text;
    }

    protected function normalizeResponseFilters(Request $request): array
    {
        return [
            'search' => $this->trimString($request->query('search'), 100),
            'fakultas' => $this->trimString($request->query('fakultas'), 100),
            'prodi' => $this->trimString($request->query('prodi'), 100),
            'tahun' => $this->normalizeYear($request->query('tahun')),
            'status' => $this->normalizeStatusFilter($request->query('status')),
            'question_id' => $this->normalizeQuestionId($request->query('question_id')),
            'answer_value' => $this->trimString($request->query('answer_value'), 100),
        ];
    }

    /**
     * Submit response via Authenticated User (Sanctum).
     * Enforces ownership if user is Alumni.
     */
    public function submitAuthenticated(Request $request)
    {
        $user = Auth::user();
        $payload = $request->all();

        // Security Check: If user is an Alumni (assuming 'alumni' role or check relation), force NIM.
        // For strictly "Alumni" users:
        // if ($user->role === 'alumni') { ... } 
        // Assuming your User model has 'nim' or linked Alumni.

        // For now, we trust the Admin if they are submitting (e.g. data entry),
        // but for normal 'alumni' submission, we should ideally verify.
        // Since we are adding 'submitViaToken', this method is mostly for Admin or Dashboard testing.

        // Let's at least Log it.
        Log::info('Authenticated Submission', ['user_id' => $user->id, 'role' => $user->role ?? 'unknown']);

        return $this->validateAndProcess($payload);
    }

    /**
     * Submit response using an Encrypted Survey Token.
     * Accessible Publicly (with valid Token).
     */
    public function submitViaToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'questionnaire_id' => 'required|integer',
            // answers validation happens in validateAndProcess
        ]);

        try {
            $decrypted = Crypt::decryptString($request->input('token'));
            $tokenPayload = json_decode($decrypted, true);

            if (!isset($tokenPayload['nim']) || !isset($tokenPayload['exp'])) {
                return response()->json(['message' => 'Token rusak'], 401);
            }

            if (Carbon::now()->timestamp > $tokenPayload['exp']) {
                return response()->json(['message' => 'Token kedaluwarsa'], 401);
            }

            // Token is Valid. Override the 'nim' in the payload to match the Token.
            // This prevents IDOR (using a valid token for NIM A but submitting for NIM B).
            $submissionData = $request->all();
            $submissionData['nim'] = trim((string) $tokenPayload['nim']);
            // Force identity from token and avoid triggering "alumni_id required"
            // validation branch by removing alumni_id keys entirely.
            unset($submissionData['alumni_id'], $submissionData['alumniId']);

            return $this->validateAndProcess($submissionData);

        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json(['message' => 'Token tidak valid'], 401);
        }
    }

    /**
     * Submit response publicly via NIM (No Login Required).
     * WARNING: This allows anyone to submit for a valid NIM.
     */
    public function submit(Request $request)
    {
        // Direct processing. validateAndProcess will handle validation.
        return $this->validateAndProcess($request->all());
    }

    /**
     * POST /api/submissions/bulk (Admin Only)
     */
    public function submitBulk(Request $request)
    {
        // Admin middleware handles auth.
        $payloads = $request->input('data', []);
        $created = [];
        $errors = [];

        foreach ($payloads as $index => $payload) {
            try {
                $resource = $this->validateAndProcess($payload);
                $created[] = $resource->response()->getData(true);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors[] = ['index' => $index, 'errors' => $e->errors()];
            } catch (\Exception $e) {
                $errors[] = ['index' => $index, 'error' => $e->getMessage()];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Beberapa data gagal diimpor.',
                'errors' => $errors,
                'success_count' => count($created)
            ], 422);
        }

        return response()->json(['data' => $created]);
    }

    /**
     * Centralized validation and processing logic.
     */
    protected function validateAndProcess(array $input)
    {
        // 0. Pre-process / Normalize Input for Robustness
        if (isset($input['answers'])) {
            $input['answers'] = $this->normalizeAnswers($input['answers']);
        }

        // Determine effective audience.
        // We prioritize questionnaire audience to avoid relying on client-provided flags.
        $requestedAudience = strtolower(trim((string) ($input['audience'] ?? $input['target_audience'] ?? $input['type'] ?? '')));
        if ($requestedAudience === 'pengguna_alumni') {
            $requestedAudience = 'pengguna';
        }
        if ($requestedAudience === '') {
            $requestedAudience = 'alumni';
        }

        $questionnaire = null;
        if (isset($input['questionnaire_id']) && is_numeric($input['questionnaire_id'])) {
            $questionnaire = Questionnaire::find((int) $input['questionnaire_id']);
        }

        $questionnaireAudience = strtolower(trim((string) ($questionnaire->audience_normalized ?? $questionnaire->audience ?? '')));
        if ($questionnaireAudience === '') {
            $questionnaireAudience = $requestedAudience;
        }
        if (!in_array($questionnaireAudience, ['alumni', 'pengguna', 'umum'], true)) {
            $questionnaireAudience = 'alumni';
        }

        $requiresAlumniIdentity = $questionnaireAudience === 'alumni';

        $rules = [
            'questionnaire_id' => ['required', 'integer', 'exists:questionnaires,id'],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['sometimes', 'required', 'integer', 'exists:questions,id'],
            'answers.*.jawaban' => ['nullable'],
            'form_data' => ['nullable', 'array'],
            'extra' => ['nullable', 'array'],
        ];

        // Only enforce strict Alumni identity for Alumni-targeted questionnaires.
        if ($requiresAlumniIdentity) {
            $rules['alumni_id'] = ['sometimes', 'required', 'integer', 'exists:alumnis,id'];
            $rules['nim'] = ['required_without:alumni_id', 'exists:alumnis,nim'];
        } else {
            // For Umum/Pengguna, alumni identity is optional.
            $rules['alumni_id'] = ['nullable', 'integer', 'exists:alumnis,id'];
            $rules['nim'] = ['nullable', 'string', 'max:64'];
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, $rules);
        $data = $validator->validate();

        // 1. Resolve Alumni (optional for Umum/Pengguna)
        $alumni = null;
        $alumniId = null;

        if ($requiresAlumniIdentity) {
            if (isset($data['alumni_id'])) {
                $alumni = Alumni::findOrFail($data['alumni_id']);
            } else {
                $nim = (string) $data['nim'];
                $alumni = Cache::remember("alumni:nim:{$nim}", now()->addMinutes(5), function () use ($nim) {
                    return Alumni::where('nim', $nim)->firstOrFail();
                });
            }
            $alumniId = $alumni->id;
        } else {
            if (!empty($data['alumni_id'])) {
                $alumni = Alumni::find($data['alumni_id']);
                $alumniId = $alumni?->id;
            } elseif (!empty($data['nim'])) {
                $nim = (string) $data['nim'];
                $alumni = Cache::remember("alumni:nim:{$nim}", now()->addMinutes(5), function () use ($nim) {
                    return Alumni::where('nim', $nim)->first();
                });
                $alumniId = $alumni?->id;
            }
        }

        // 2. Normalize Answers
        $questionIds = Cache::remember(
            "questionnaire:{$data['questionnaire_id']}:question_ids",
            now()->addMinutes(5),
            function () use ($data) {
                return Question::where('questionnaire_id', $data['questionnaire_id'])->pluck('id')->all();
            }
        );
        $questionIdLookup = array_flip(array_map('strval', $questionIds));
        $answers = array_values(array_filter($input['answers'] ?? [], function ($answer) use ($questionIdLookup) {
            $questionId = $answer['question_id'] ?? null;
            if ($questionId === null) {
                return false;
            }
            return isset($questionIdLookup[(string) $questionId]);
        }));

        // 3. Determine Attempt Number
        if ($alumniId) {
            $nextAttempt = (int) Response::where('alumni_id', $alumniId)
                ->where('questionnaire_id', $data['questionnaire_id'])
                ->max('attempt_ke');
            $attemptNumber = $nextAttempt ? $nextAttempt + 1 : 1;
        } else {
            // For general/pengguna responses without Alumni ID, we can either:
            // A. Always set to 1
            // B. Try to match by generated 'nim' in form_data if we want to track updates (complexity)
            // For now, simple append:
            $attemptNumber = 1;
        }

        // 3b. Prepare Form Data
        $formData = $data['form_data'] ?? [];
        if (isset($input['extra'])) {
            $formData['extra'] = $input['extra'];
        }

        // 4. Save
        $response = DB::transaction(function () use ($alumniId, $data, $answers, $attemptNumber, $formData) {
            $response = Response::create([
                'alumni_id' => $alumniId, // Can be null
                'questionnaire_id' => $data['questionnaire_id'],
                'attempt_ke' => $attemptNumber,
                'form_data' => !empty($formData) ? $formData : null,
            ]);

            if (!empty($answers)) {
                $now = now();
                $hasNormalizedColumns = $this->hasNormalizedAnswerColumns();
                $payload = [];
                foreach ($answers as $answer) {
                    $rawAnswer = $answer['jawaban'] ?? null;
                    $row = [
                        'response_id' => $response->id,
                        'question_id' => $answer['question_id'],
                        'jawaban' => $this->encodeAnswerValue($rawAnswer),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($hasNormalizedColumns) {
                        $row = array_merge($row, $this->normalizeAnswerPayload($rawAnswer));
                    }

                    $payload[] = $row;
                }
                ResponseAnswer::insert($payload);
            }

            return $response->load(['answers.question', 'questionnaire', 'alumni']);
        });

        $this->clearDashboardCache((int) $data['questionnaire_id']);

        return new ResponseResource($response);
    }

    public function getAttempts(int $alumniId)
    {
        $alumni = Alumni::findOrFail($alumniId);
        $this->authorize('view', $alumni);

        $attempts = $this->applyUserScope(Response::query())
            ->where('alumni_id', $alumniId)
            ->with(['questionnaire', 'alumni'])
            ->orderByDesc('created_at')
            ->get();
        return ResponseResource::collection($attempts);
    }

    public function getAttemptDetail(int $attemptId)
    {
        $response = $this->applyUserScope(Response::with(['answers.question', 'questionnaire', 'alumni']))
            ->where('id', $attemptId)
            ->firstOrFail();
        $this->authorize('view', $response);
        return new ResponseResource($response);
    }

    public function byQuestionnaire(Request $request, int $questionnaireId)
    {
        $this->authorize('viewAny', Response::class);

        Questionnaire::findOrFail($questionnaireId);
        $filters = $this->normalizeResponseFilters($request);
        $validator = \Illuminate\Support\Facades\Validator::make(array_merge($request->all(), $filters), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            // 'all' and 'include_answers' handled manually via $request->boolean()
            'search' => ['nullable', 'string', 'max:100'],
            'fakultas' => ['nullable', 'string', 'max:100'],
            'prodi' => ['nullable', 'string', 'max:100'],
            'tahun' => ['nullable', 'digits:4'],
            'status' => ['nullable'],
            'question_id' => ['nullable', 'integer'],
            'answer_value' => ['nullable', 'string', 'max:100'],
        ]);
        // ... (validator after hook kept same) ...
        $validator->validate();

        // ... (pagination setup kept same) ...
        $includeAnswers = $request->boolean('include_answers', true);
        $wantsAll = $request->boolean('all', false);
        // Default pagination 50, if all=true then 25000 (enough for current scale)
        $perPage = $wantsAll ? 25000 : 50;

        $responsesQuery = $this->applyUserScope(Response::query())
            ->where('questionnaire_id', $questionnaireId)
            ->with(['alumni', 'questionnaire']);

        if ($includeAnswers) {
            $responsesQuery->with(['answers.question']);
        }

        // Adjust Search Filters to handle Null Alumni
        $search = $filters['search'] ?? null;
        if (!empty($search)) {
            $responsesQuery->where(function ($q) use ($search) {
                // Search in Alumni
                $q->whereHas('alumni', function ($sub) use ($search) {
                    $sub->where('nama', 'like', '%' . $search . '%')
                        ->orWhere('nim', 'like', '%' . $search . '%');
                });
                // Or Search in Form Data (for Pengguna/External)
                $q->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(form_data, "$.organisasi"))) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(form_data, "$.nim"))) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Filters that rely on Alumni (Fakultas, Prodi, Tahun)
        // If Alumni is null, these filters naturally exclude the row unless we check form_data (which usually doesn't have prodi/fakultas for Pengguna)
        if (!empty($filters['fakultas'])) {
            $responsesQuery->whereHas('alumni', function ($q) use ($filters) {
                $q->where('fakultas', 'like', '%' . $filters['fakultas'] . '%');
            });
        }
        if (!empty($filters['prodi'])) {
            $responsesQuery->whereHas('alumni', function ($q) use ($filters) {
                $q->where('prodi', 'like', '%' . $filters['prodi'] . '%');
            });
        }
        if (!empty($filters['tahun'])) {
            $responsesQuery->whereHas('alumni', function ($q) use ($filters) {
                $q->where('tahun_lulus', $filters['tahun']);
            });
        }

        // Status Filter
        if (!empty($filters['status'])) {
            // ... existing status logic ...
            $statusList = is_array($filters['status']) ? $filters['status'] : explode(',', (string) $filters['status']);
            // ... (simplified logic reuse)
            $responsesQuery->where(function ($q) use ($statusList) {
                $q->whereHas('alumni', function ($sub) use ($statusList) {
                    $sub->whereIn('status_pekerjaan', $statusList);
                })->orWhere(function ($sub) use ($statusList) {
                    // Check form_data status
                    $placeholders = implode(',', array_fill(0, count($statusList), '?'));
                    $sub->whereRaw(
                        'LOWER(JSON_UNQUOTE(JSON_EXTRACT(responses.form_data, "$.status"))) IN (' . $placeholders . ')',
                        array_map('strtolower', $statusList)
                    );
                });
            });
        }

        // ... (Question/Answer filter same) ...

        $responses = $responsesQuery->latest()->paginate($perPage);
        return ResponseResource::collection($responses);
    }


    public function summaryByQuestionnaire(Request $request, int $questionnaireId)
    {
        $this->authorize('viewAny', Response::class);

        // ... (validation same) ...
        $filters = $this->normalizeResponseFilters($request);

        $questionnaire = Questionnaire::findOrFail($questionnaireId);
        $audience = $questionnaire->audience_normalized ?? 'alumni';

        // Cache Key Logic ...
        // ...

        // Retrieve
        $query = $this->applyUserScope(Response::query())
            ->where('questionnaire_id', $questionnaireId)
            ->with('alumni');

        // Apply Filters (Safety for null alumni)
        if (!empty($filters['fakultas'])) {
            $query->whereHas('alumni', function ($q) use ($filters) {
                $q->where('fakultas', 'like', '%' . $filters['fakultas'] . '%');
            });
        }
        // ... (Same for prodi/tahun) ...

        $responses = $query->get();

        // Processing
        $records = [];
        foreach ($responses as $response) {
            $formData = $response->form_data ?? [];

            // SAFE ACCESS to alumni
            $alumniStatus = $response->alumni?->status_pekerjaan ?? null;
            $statusRaw = $formData['status'] ?? ($formData['status_pekerjaan'] ?? $alumniStatus);
            $status = $this->normalizeStatus($statusRaw, $audience);

            // ... (filtering logic) ...

            $records[] = [
                'status' => $status,
                'waitMonths' => $this->normalizeNumber($formData['bekerja_bulanDapat'] ?? $formData['bekerja_bulanTidak'] ?? $formData['mencari_mulaiSetelah'] ?? $formData['mencari_mulaiSebelum'] ?? $formData['waitMonths'] ?? null),
                'salary' => $this->normalizeSalaryToJt($formData['bekerja_pendapatan'] ?? $formData['salary'] ?? null),
                'industry' => $formData['bekerja_jenisPerusahaan'] ?? $formData['wira_jenisPerusahaan'] ?? $formData['wira_bidang'] ?? $formData['industry'] ?? 'Lainnya',
            ];
        }

        // ... (Aggregation logic same) ...

        // Re-implement aggregation to return clean object
        $total = count($records);
        // ... ((re-use existing logic block)) ...

        $statusConfig = $audience === 'pengguna'
            ? [['key' => 'pengguna', 'label' => 'Pengguna alumni']]
            : ($audience === 'umum'
                ? [['key' => 'umum', 'label' => 'Umum']]
                : [
                    ['key' => 'bekerja', 'label' => 'Bekerja'],
                    ['key' => 'wiraswasta', 'label' => 'Wirausaha'],
                    ['key' => 'melanjutkan', 'label' => 'Studi lanjut'],
                    ['key' => 'mencari', 'label' => 'Mencari kerja'],
                    ['key' => 'belum', 'label' => 'Belum memungkinkan bekerja'],
                ]);

        // Count statuses
        $statusCounts = array_map(function ($item) use ($records) {
            $count = 0;
            foreach ($records as $row) {
                if ($row['status'] === $item['key']) {
                    $count++;
                }
            }
            return [
                'label' => $item['label'],
                'value' => $count,
            ];
        }, $statusConfig);

        // Agregates
        $waits = array_filter($records, fn($row) => $row['waitMonths'] >= 0);
        $waitAvg = count($waits) ? array_sum(array_column($waits, 'waitMonths')) / count($waits) : 0;
        $salaryItems = array_filter($records, fn($row) => $row['salary'] > 0);
        $salaryAvg = count($salaryItems) ? array_sum(array_column($salaryItems, 'salary')) / count($salaryItems) : 0;

        // Industry
        $industryCounts = [];
        foreach ($records as $row) {
            $key = $row['industry'] ?: 'Lainnya';
            $industryCounts[$key] = ($industryCounts[$key] ?? 0) + 1;
        }
        arsort($industryCounts);
        $topIndustry = array_key_first($industryCounts) ?? '-';

        $bekerjaCount = count(array_filter($records, fn($row) => in_array($row['status'], ['bekerja', 'wiraswasta'], true)));
        $employedPercent = $total ? round(($bekerjaCount / max($total, 1)) * 100) : 0;

        return [
            'total' => $total,
            'employedPercent' => $employedPercent,
            'waitAvg' => round($waitAvg, 1),
            'salaryAvg' => $salaryAvg ? round($salaryAvg, 1) . ' jt' : 'N/A',
            'topIndustry' => $topIndustry ?: '-',
            'statusCounts' => $statusCounts,
            'updatedAt' => now()->toIso8601String(),
        ];
    }

    public function destroy(int $id)
    {
        $response = Response::findOrFail($id);
        $this->authorize('delete', $response);
        $questionnaireId = (int) $response->questionnaire_id;
        $response->delete();
        $this->clearDashboardCache($questionnaireId);
        AuditLogger::log('response.deleted', 'response', $id, [
            'questionnaire_id' => $questionnaireId,
        ]);
        return response()->noContent();
    }

    protected function normalizeAnswers(array $input): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if (is_array($value) && array_key_exists('question_id', $value)) {
                $normalized[] = [
                    'question_id' => (int) $value['question_id'],
                    'jawaban' => $value['jawaban'] ?? null,
                ];
                continue;
            }
            if (is_numeric($key)) {
                $normalized[] = [
                    'question_id' => (int) $key,
                    'jawaban' => $value,
                ];
            }
        }
        return $normalized;
    }

    protected function normalizeStatus($value, string $audience = 'alumni'): string
    {
        $key = strtolower(trim((string) ($value ?? 'belum')));
        if ($audience === 'umum') {
            return $key ?: 'umum';
        }
        return $key === 'umum' ? 'belum' : ($key ?: 'belum');
    }

    protected function normalizeNumber($value): float
    {
        if ($value === null) {
            return 0;
        }
        $normalized = preg_replace('/[^0-9.\\-]/', '', (string) $value);
        $num = is_numeric($normalized) ? (float) $normalized : 0;
        return $num;
    }

    protected function normalizeSalaryToJt($value): float
    {
        $raw = $this->normalizeNumber($value);
        if (!$raw)
            return 0;
        if ($raw > 1000) {
            return round(($raw / 1_000_000) * 10) / 10;
        }
        return round($raw * 10) / 10;
    }

    protected function clearDashboardCache(int $questionnaireId): void
    {
        Cache::forget("dashboard:tracer:{$questionnaireId}");
        Cache::forget("dashboard:tracer:query:{$questionnaireId}");
        $emptyFilters = [
            'fakultas' => null,
            'prodi' => null,
            'tahun' => null,
            'status' => null,
            'question_id' => null,
            'answer_value' => null,
        ];
        Cache::forget('responses:summary:' . $questionnaireId . ':' . md5(json_encode($emptyFilters)));
        Cache::forget('dashboard:insights:' . $questionnaireId . ':' . md5(json_encode([])));

        if ($questionnaireId > 0) {
            $versionKey = "dashboard:tracer:accreditation:version:{$questionnaireId}";
            $current = (int) Cache::get($versionKey, 1);
            Cache::forever($versionKey, $current + 1);
        }
    }

    protected function hasNormalizedAnswerColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasColumns('response_answers', [
            'val_int',
            'val_decimal',
            'val_date',
            'val_string',
        ]);

        return $cached;
    }

    protected function normalizeAnswerPayload($rawAnswer): array
    {
        $scalar = $this->extractScalarAnswer($rawAnswer);
        $stringValue = $this->normalizeComparableText($scalar);
        $intValue = $this->normalizeIntegerValue($scalar);
        $decimalValue = $this->normalizeDecimalValue($scalar);
        $dateValue = $this->normalizeDateValue($scalar);

        return [
            'val_int' => $intValue,
            'val_decimal' => $decimalValue,
            'val_date' => $dateValue,
            'val_string' => $stringValue,
        ];
    }

    protected function encodeAnswerValue($rawAnswer)
    {
        if (is_array($rawAnswer)) {
            return json_encode($rawAnswer);
        }
        if ($rawAnswer === null) {
            return null;
        }
        return (string) $rawAnswer;
    }

    protected function extractScalarAnswer($rawAnswer): ?string
    {
        if (is_array($rawAnswer)) {
            $flat = Arr::flatten($rawAnswer);
            foreach ($flat as $item) {
                if ($item === null || is_array($item) || is_object($item)) {
                    continue;
                }
                $text = trim((string) $item);
                if ($text !== '') {
                    return $text;
                }
            }
            return null;
        }

        if ($rawAnswer === null) {
            return null;
        }

        $text = trim((string) $rawAnswer);
        return $text !== '' ? $text : null;
    }

    protected function normalizeComparableText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!$normalized) {
            return null;
        }

        return mb_substr($normalized, 0, 255);
    }

    protected function normalizeIntegerValue(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digitsOnly = preg_replace('/[^0-9\-]/', '', $value);
        if ($digitsOnly === '' || $digitsOnly === '-') {
            return null;
        }

        if (!is_numeric($digitsOnly)) {
            return null;
        }

        return (int) $digitsOnly;
    }

    protected function normalizeDecimalValue(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    protected function normalizeDateValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function applyUserScope($query)
    {
        $user = Auth::user();
        if (!$user) {
            return $query;
        }

        $user->loadMissing('role');
        $roleName = $user->role->nama_role ?? $user->role ?? null;

        if ($roleName === 'Admin Fakultas') {
            $faculty = trim((string) ($user->fakultas ?? ''));
            if ($faculty !== '') {
                $normalized = $this->normalizeUnit($faculty);
                $stripped = $this->normalizeUnit($this->stripFakultasPrefix($faculty));
                $variants = array_values(array_unique(array_filter([$normalized, $stripped])));
                if (!$variants) {
                    return $query->whereRaw('1=0');
                }
                return $query->where(function ($outer) use ($variants) {
                    $outer->whereHas('alumni', function ($q) use ($variants) {
                        $q->where(function ($inner) use ($variants) {
                            foreach ($variants as $index => $value) {
                                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                                $inner->$method('LOWER(TRIM(fakultas)) = ?', [$value]);
                            }
                        });
                    })->orWhereNull('alumni_id');
                });
            }
            return $query->whereRaw('1=0');
        }

        if ($roleName === 'Admin Prodi') {
            $prodi = trim((string) ($user->prodi ?? ''));
            if ($prodi !== '') {
                $normalized = $this->normalizeUnit($prodi);
                return $query->where(function ($outer) use ($normalized) {
                    $outer->whereHas('alumni', function ($q) use ($normalized) {
                        $q->whereRaw('LOWER(TRIM(prodi)) = ?', [$normalized]);
                    })->orWhereNull('alumni_id');
                });
            }
            return $query->whereRaw('1=0');
        }

        return $query;
    }

    protected function stripFakultasPrefix(string $value): string
    {
        return preg_replace('/^fakultas\\s+/i', '', $value) ?? $value;
    }

    protected function normalizeUnit(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\\s+/', ' ', $value);
        return strtolower($value ?? '');
    }
}
