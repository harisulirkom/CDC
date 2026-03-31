<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\Response;
use App\Models\ResponseAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        $summary = $this->tracerSummary($questionnaireId)->getData(true);

        $statusCounts = $summary['status_chart'] ?? [];
        $pendapatanBuckets = $summary['pendapatan_chart']['buckets'] ?? [];
        $lokasiData = $summary['lokasi_chart']['data'] ?? [];

        $pendapatan = [];
        foreach ($pendapatanBuckets as $bucket) {
            $pendapatan[$bucket['label']] = $bucket['total'] ?? 0;
        }

        $lokasiProvinsi = [];
        foreach ($lokasiData as $row) {
            $lokasiProvinsi[$row['provinsi']] = $row['total'] ?? 0;
        }

        return response()->json([
            'totalRespondents' => $summary['summary']['total_respondents'] ?? 0,
            'statusCounts' => [
                'bekerja' => $statusCounts['bekerja'] ?? 0,
                'belum_bekerja' => $statusCounts['belum_bekerja'] ?? 0,
                'wirausaha' => $statusCounts['wirausaha'] ?? 0,
                'studi_lanjut' => $statusCounts['studi_lanjut'] ?? 0,
            ],
            'pendapatan' => $pendapatan,
            'lokasiProvinsi' => $lokasiProvinsi,
        ]);
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
}
