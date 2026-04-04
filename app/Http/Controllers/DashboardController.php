<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\Response;
use App\Models\ResponseAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/summary
     * Endpoint ringkas untuk halaman Ikhtisar admin.
     */
    public function adminSummary(Request $request)
    {
        $totalAlumni = Alumni::query()->count();
        $totalResponses = Response::query()->count();
        $totalAlumniResponses = Response::query()->whereNotNull('alumni_id')->count();
        $totalPenggunaResponses = Response::query()
            ->whereHas('questionnaire', function ($q) {
                $q->where('audience', 'pengguna');
            })
            ->count();

        return response()->json([
            'overview' => [
                'totalAlumni' => $totalAlumni,
                'totalResponses' => $totalResponses,
                'alumniResponses' => $totalAlumniResponses,
                'penggunaResponses' => $totalPenggunaResponses,
            ],
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/dashboard/tracer/{questionnaire_id}
     */
    public function tracerSummary(int $questionnaireId)
    {
        $questionnaire = Questionnaire::find($questionnaireId);

        if (! $questionnaire) {
            return response()->json(['message' => 'Questionnaire not found'], 404);
        }

        $cacheKey = "dashboard:tracer:{$questionnaireId}";
        $payload = Cache::remember($cacheKey, 60, function () use ($questionnaireId, $questionnaire) {
            $totalRespondents = Response::where('questionnaire_id', $questionnaireId)
                ->distinct('alumni_id')
                ->count('alumni_id');

            $statusChart = $this->buildStatusChart($questionnaireId);
            $avgWaitingMonths = $this->calculateAverageWaitingMonths($questionnaireId);
            [$incomeBuckets, $incomeQuestionId] = $this->buildIncomeBuckets($questionnaireId);
            [$locationChart, $locationQuestionId] = $this->buildLocationChart($questionnaireId);

            return [
                'summary' => [
                    'questionnaire_id' => $questionnaireId,
                    'questionnaire_title' => $questionnaire->judul,
                    'total_respondents' => $totalRespondents,
                    'avg_waiting_months' => $avgWaitingMonths,
                ],
                'status_chart' => $statusChart,
                'pendapatan_chart' => [
                    'question_id' => $incomeQuestionId,
                    'buckets' => $incomeBuckets,
                ],
                'lokasi_chart' => [
                    'question_id' => $locationQuestionId,
                    'data' => $locationChart,
                ],
            ];
        });

        return response()->json($payload);
    }

    /**
     * GET /api/dashboard/tracer?questionnaire_id=...
     * Mengembalikan format ringkas yang dipakai SPA (totalRespondents, statusCounts, pendapatan, lokasiProvinsi).
     */
    public function tracerSummaryQuery(Request $request)
    {
        $questionnaireId = (int) ($request->query('questionnaire_id') ?? 0);

        if (! $questionnaireId) {
            $questionnaireId = Questionnaire::query()->orderByDesc('id')->value('id') ?? 0;
        }

        if (! $questionnaireId) {
            return response()->json([
                'totalRespondents' => 0,
                'statusCounts' => [
                    'bekerja' => 0,
                    'belum_bekerja' => 0,
                    'wirausaha' => 0,
                    'studi_lanjut' => 0,
                ],
                'pendapatan' => [],
                'lokasiProvinsi' => [],
            ]);
        }

        $cacheKey = "dashboard:tracer:query:{$questionnaireId}";
        $payload = Cache::remember($cacheKey, 60, function () use ($questionnaireId) {
            return $this->buildTracerSummaryQueryPayload($questionnaireId);
        });

        return response()->json($payload);
    }

    protected function buildTracerSummaryQueryPayload(int $questionnaireId): array
    {
        $base = Response::query()->where('questionnaire_id', $questionnaireId);

        $totalRespondents = (clone $base)
            ->whereNotNull('alumni_id')
            ->distinct('alumni_id')
            ->count('alumni_id');

        $statusCounts = $this->buildStatusCountsFast($questionnaireId);
        $pendapatan = $this->buildIncomeMapFast($questionnaireId);
        $lokasiProvinsi = $this->buildLocationMapFast($questionnaireId);

        return [
            'totalRespondents' => $totalRespondents,
            'statusCounts' => $statusCounts,
            'pendapatan' => $pendapatan,
            'lokasiProvinsi' => $lokasiProvinsi,
        ];
    }

    protected function buildStatusCountsFast(int $questionnaireId): array
    {
        $rows = Response::query()
            ->where('responses.questionnaire_id', $questionnaireId)
            ->whereNotNull('responses.alumni_id')
            ->join('alumnis', 'alumnis.id', '=', 'responses.alumni_id')
            ->selectRaw('LOWER(COALESCE(alumnis.status_pekerjaan, "unknown")) as status_key, COUNT(DISTINCT responses.alumni_id) as total')
            ->groupBy('status_key')
            ->get();

        $result = [
            'bekerja' => 0,
            'belum_bekerja' => 0,
            'wirausaha' => 0,
            'studi_lanjut' => 0,
        ];

        foreach ($rows as $row) {
            $key = (string) $row->status_key;
            $total = (int) ($row->total ?? 0);

            if (str_contains($key, 'wira') || str_contains($key, 'usaha')) {
                $result['wirausaha'] += $total;
                continue;
            }
            if (str_contains($key, 'studi') || str_contains($key, 'kuliah')) {
                $result['studi_lanjut'] += $total;
                continue;
            }
            if (str_contains($key, 'belum') || str_contains($key, 'tidak')) {
                $result['belum_bekerja'] += $total;
                continue;
            }
            if (str_contains($key, 'bekerja')) {
                $result['bekerja'] += $total;
            }
        }

        return $result;
    }

    protected function buildIncomeMapFast(int $questionnaireId): array
    {
        $questionId = $this->findQuestionIdByAnyKeyword($questionnaireId, ['gaji', 'pendapatan', 'income']);
        if (! $questionId) {
            return [];
        }

        $hasNormalized = $this->hasAnswerNormalizedColumns();
        $amountExpr = $hasNormalized
            ? 'CASE
                WHEN response_answers.val_int IS NOT NULL AND response_answers.val_int > 100000 THEN response_answers.val_int
                WHEN response_answers.val_decimal IS NOT NULL AND response_answers.val_decimal > 0 AND response_answers.val_decimal <= 1000 THEN response_answers.val_decimal * 1000000
                WHEN response_answers.val_int IS NOT NULL AND response_answers.val_int > 0 AND response_answers.val_int <= 1000 THEN response_answers.val_int * 1000000
                ELSE CASE
                    WHEN CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2)) <= 1000
                        THEN CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2)) * 1000000
                    ELSE CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2))
                END
            END'
            : 'CASE
                WHEN CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2)) <= 1000
                    THEN CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2)) * 1000000
                ELSE CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(20,2))
            END';

        $row = ResponseAnswer::query()
            ->join('responses', 'responses.id', '=', 'response_answers.response_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->where('response_answers.question_id', $questionId)
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN {$amountExpr} < 2000000 THEN response_answers.response_id END) as lt_2jt,
                COUNT(DISTINCT CASE WHEN {$amountExpr} >= 2000000 AND {$amountExpr} < 4000000 THEN response_answers.response_id END) as btw_2_4jt,
                COUNT(DISTINCT CASE WHEN {$amountExpr} >= 4000000 AND {$amountExpr} < 6000000 THEN response_answers.response_id END) as btw_4_6jt,
                COUNT(DISTINCT CASE WHEN {$amountExpr} >= 6000000 THEN response_answers.response_id END) as gt_6jt
            ")
            ->first();

        return [
            '< 2jt' => (int) ($row->lt_2jt ?? 0),
            '2-4jt' => (int) ($row->btw_2_4jt ?? 0),
            '4-6jt' => (int) ($row->btw_4_6jt ?? 0),
            '> 6jt' => (int) ($row->gt_6jt ?? 0),
        ];
    }

    protected function buildLocationMapFast(int $questionnaireId): array
    {
        $questionId = $this->findQuestionIdByAnyKeyword($questionnaireId, ['provinsi', 'lokasi', 'domisili']);
        if (! $questionId) {
            return [];
        }

        $locationExpr = $this->hasAnswerNormalizedColumns()
            ? 'COALESCE(NULLIF(TRIM(response_answers.val_string), ""), LOWER(TRIM(response_answers.jawaban)))'
            : 'LOWER(TRIM(response_answers.jawaban))';

        $rows = ResponseAnswer::query()
            ->join('responses', 'responses.id', '=', 'response_answers.response_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->where('response_answers.question_id', $questionId)
            ->selectRaw("{$locationExpr} as provinsi, COUNT(DISTINCT response_answers.response_id) as total")
            ->groupByRaw($locationExpr)
            ->orderByDesc('total')
            ->limit(50)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row->provinsi ?? ''));
            if ($name === '') {
                $name = 'lainnya';
            }
            $result[$name] = (int) ($row->total ?? 0);
        }

        return $result;
    }

    protected function buildStatusChart(int $questionnaireId): array
    {
        $rows = Response::query()
            ->where('responses.questionnaire_id', $questionnaireId)
            ->join('alumnis', 'alumnis.id', '=', 'responses.alumni_id')
            ->selectRaw('LOWER(COALESCE(alumnis.status_pekerjaan, "unknown")) as status_key, COUNT(DISTINCT responses.id) as total')
            ->groupBy('status_key')
            ->get();

        $result = [
            'bekerja' => 0,
            'belum_bekerja' => 0,
            'studi_lanjut' => 0,
            'wirausaha' => 0,
            'unknown' => 0,
        ];

        foreach ($rows as $row) {
            $status = $row->status_key;
            $total = (int) $row->total;

            if (str_contains($status, 'bekerja') && ! str_contains($status, 'belum')) {
                $result['bekerja'] += $total;
            } elseif (str_contains($status, 'belum') || str_contains($status, 'tidak')) {
                $result['belum_bekerja'] += $total;
            } elseif (str_contains($status, 'studi') || str_contains($status, 'kuliah')) {
                $result['studi_lanjut'] += $total;
            } elseif (str_contains($status, 'usaha') || str_contains($status, 'wira')) {
                $result['wirausaha'] += $total;
            } else {
                $result['unknown'] += $total;
            }
        }

        return $result;
    }

    protected function calculateAverageWaitingMonths(int $questionnaireId): ?float
    {
        $questionId = $this->findQuestionIdByKeywords($questionnaireId, ['bulan', 'pekerjaan']);

        if (! $questionId) {
            return null;
        }

        $avg = ResponseAnswer::query()
            ->join('responses', 'responses.id', '=', 'response_answers.response_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->where('response_answers.question_id', $questionId)
            ->selectRaw('AVG(CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS DECIMAL(10,2))) as avg_wait')
            ->value('avg_wait');

        return $avg ? round((float) $avg, 2) : null;
    }

    protected function buildIncomeBuckets(int $questionnaireId): array
    {
        $questionId = $this->findQuestionIdByKeywords($questionnaireId, ['gaji', 'pendapatan', 'income']);

        if (! $questionId) {
            return [[], null];
        }

        $amountExpr = 'CAST(NULLIF(REGEXP_REPLACE(response_answers.jawaban, "[^0-9.]", ""), "") AS UNSIGNED)';

        $bucket = ResponseAnswer::query()
            ->join('responses', 'responses.id', '=', 'response_answers.response_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->where('response_answers.question_id', $questionId)
            ->selectRaw("
                SUM(CASE WHEN {$amountExpr} < 2000000 THEN 1 ELSE 0 END) as lt_2jt,
                SUM(CASE WHEN {$amountExpr} >= 2000000 AND {$amountExpr} < 4000000 THEN 1 ELSE 0 END) as btw_2_4jt,
                SUM(CASE WHEN {$amountExpr} >= 4000000 AND {$amountExpr} < 6000000 THEN 1 ELSE 0 END) as btw_4_6jt,
                SUM(CASE WHEN {$amountExpr} >= 6000000 THEN 1 ELSE 0 END) as gt_6jt
            ")
            ->first();

        return [
            [
                'label' => '< 2jt',
                'total' => (int) ($bucket->lt_2jt ?? 0),
            ],
            [
                'label' => '2-4jt',
                'total' => (int) ($bucket->btw_2_4jt ?? 0),
            ],
            [
                'label' => '4-6jt',
                'total' => (int) ($bucket->btw_4_6jt ?? 0),
            ],
            [
                'label' => '> 6jt',
                'total' => (int) ($bucket->gt_6jt ?? 0),
            ],
        ];
    }

    protected function buildLocationChart(int $questionnaireId): array
    {
        $questionId = $this->findQuestionIdByKeywords($questionnaireId, ['provinsi', 'lokasi', 'domisili']);

        if (! $questionId) {
            return [[], null];
        }

        $rows = ResponseAnswer::query()
            ->join('responses', 'responses.id', '=', 'response_answers.response_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->where('response_answers.question_id', $questionId)
            ->selectRaw('LOWER(TRIM(response_answers.jawaban)) as provinsi, COUNT(DISTINCT response_answers.response_id) as total')
            ->groupBy('provinsi')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'provinsi' => $row->provinsi ?: 'lainnya',
                    'total' => (int) $row->total,
                ];
            })
            ->all();

        return [$rows, $questionId];
    }

    protected function findQuestionIdByKeywords(int $questionnaireId, array $keywords): ?int
    {
        $query = Question::where('questionnaire_id', $questionnaireId);

        foreach ($keywords as $keyword) {
            $query->whereRaw('LOWER(pertanyaan) LIKE ?', ['%' . strtolower($keyword) . '%']);
        }

        $question = $query->first();

        return $question?->id;
    }

    protected function findQuestionIdByAnyKeyword(int $questionnaireId, array $keywords): ?int
    {
        if (empty($keywords)) {
            return null;
        }

        $cacheKey = 'dashboard:tracer:qid:' . $questionnaireId . ':kw:' . md5(json_encode($keywords));

        return Cache::remember($cacheKey, 600, function () use ($questionnaireId, $keywords) {
            $query = Question::query()->where('questionnaire_id', $questionnaireId);
            $query->where(function ($sub) use ($keywords) {
                foreach ($keywords as $index => $keyword) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $sub->$method('LOWER(pertanyaan) LIKE ?', ['%' . strtolower($keyword) . '%']);
                }
            });

            $question = $query->orderBy('id')->first();
            return $question?->id;
        });
    }

    protected function hasAnswerNormalizedColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasColumns('response_answers', ['val_int', 'val_decimal', 'val_string']);
        return $cached;
    }
}
