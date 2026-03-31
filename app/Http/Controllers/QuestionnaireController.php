<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionnaireRequest;
use App\Http\Requests\UpdateQuestionnaireRequest;
use App\Http\Resources\QuestionnaireResource;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class QuestionnaireController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Questionnaire::class);

        $audFilter = request()->has('audience')
            ? $this->normalizeAudience(request('audience'))
            : null;

        $questionnaires = Questionnaire::query()
            ->withCount('questions')
            ->when($audFilter, fn($q) => $q->where('audience', $audFilter))
            ->when(request('active'), fn($q) => $q->where('is_active', (bool) request('active')))
            ->latest()
            ->paginate(10);

        return QuestionnaireResource::collection($questionnaires);
    }

    public function store(StoreQuestionnaireRequest $request)
    {
        $this->authorize('create', Questionnaire::class);

        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            $questionnaire = Questionnaire::create($this->normalizeMeta($data));

            $this->applyActiveFlag($questionnaire);

            $questions = collect($request->input('questions', []))->map(function ($question, $index) {
                return [
                    'pertanyaan' => $question['pertanyaan'] ?? $question['label'] ?? $question['question'] ?? '',
                    'tipe' => $question['tipe'] ?? $question['type'] ?? 'text',
                    'pilihan' => $question['pilihan'] ?? $question['options'] ?? null,
                    'is_required' => $question['is_required'] ?? $question['isRequired'] ?? $question['required'] ?? false,
                    'status_condition' => $question['status_condition'] ?? $question['statusCondition'] ?? 'all',
                    'urutan' => $question['urutan'] ?? $index,
                ];
            })->all();

            if ($questions) {
                $questionnaire->questions()->createMany($questions);
            }

            $this->clearQuestionnaireCache($questionnaire);
            AuditLogger::log('questionnaire.created', 'questionnaire', $questionnaire->id);

            return new QuestionnaireResource($questionnaire->fresh('questions'));
        });
    }

    public function show(Questionnaire $questionnaire)
    {
        $questionnaire->load('questions');

        return new QuestionnaireResource($questionnaire);
    }

    public function update(UpdateQuestionnaireRequest $request, Questionnaire $questionnaire)
    {
        $this->authorize('update', $questionnaire);

        return DB::transaction(function () use ($request, $questionnaire) {
            $data = $request->validated();

            $questionnaire->update($this->normalizeMeta($data, $questionnaire));

            if ($request->has('questions')) {
                $questionnaire->questions()->delete();
                $questions = collect($request->input('questions', []))->map(function ($question, $index) {
                    return [
                        'pertanyaan' => $question['pertanyaan'] ?? $question['label'] ?? $question['question'] ?? '',
                        'tipe' => $question['tipe'] ?? $question['type'] ?? 'text',
                        'pilihan' => $question['pilihan'] ?? $question['options'] ?? null,
                        'is_required' => $question['is_required'] ?? $question['isRequired'] ?? $question['required'] ?? false,
                        'status_condition' => $question['status_condition'] ?? $question['statusCondition'] ?? 'all',
                        'urutan' => $question['urutan'] ?? $index,
                    ];
                })->all();
                if ($questions) {
                    $questionnaire->questions()->createMany($questions);
                }
            }

            $this->applyActiveFlag($questionnaire);
            $this->clearQuestionnaireCache($questionnaire);

            AuditLogger::log('questionnaire.updated', 'questionnaire', $questionnaire->id);

            return new QuestionnaireResource($questionnaire->fresh('questions'));
        });
    }

    public function destroy(Questionnaire $questionnaire)
    {
        $this->authorize('delete', $questionnaire);

        $this->clearQuestionnaireCache($questionnaire);
        $questionnaire->delete();
        AuditLogger::log('questionnaire.deleted', 'questionnaire', $questionnaire->id);

        return response()->noContent();
    }

    public function active(Request $request)
    {
        $audience = $this->normalizeAudience($request->query('audience', 'alumni'));

        $active = Cache::remember("questionnaire:active:{$audience}", now()->addSeconds(30), function () use ($audience) {
            $active = Questionnaire::where('audience', $audience)
                ->where('is_active', true)
                ->with('questions')
                ->latest()
                ->first();

            if (!$active) {
                $active = Questionnaire::where('audience', $audience)
                    ->with('questions')
                    ->latest()
                    ->first();
            }

            return $active;
        });

        if (!$active) {
            return response()->json(['message' => 'Tidak ada kuisioner aktif'], 404);
        }

        return new QuestionnaireResource($active);
    }

    protected function normalizeMeta(array $data, ?Questionnaire $current = null): array
    {
        $data['audience'] = $this->normalizeAudience($data['audience'] ?? $current?->audience ?? null);
        $data['chip_text'] = $data['chip_text'] ?? ($data['chipText'] ?? null);
        $data['estimated_time'] = $data['estimated_time'] ?? ($data['estimatedTime'] ?? null);
        if (array_key_exists('active', $data)) {
            $data['is_active'] = (bool) $data['active'];
        }
        if (array_key_exists('chipText', $data)) {
            unset($data['chipText']);
        }
        if (array_key_exists('estimatedTime', $data)) {
            unset($data['estimatedTime']);
        }
        if (array_key_exists('extra_questions', $data) === false && array_key_exists('extraQuestions', $data)) {
            $data['extra_questions'] = $data['extraQuestions'];
        }
        unset($data['extraQuestions']);

        return $data;
    }

    protected function normalizeAudience(?string $audience): string
    {
        $audience = strtolower(trim($audience ?? ''));
        return in_array($audience, ['alumni', 'pengguna', 'umum'], true) ? $audience : 'alumni';
    }

    protected function clearQuestionnaireCache(?Questionnaire $questionnaire): void
    {
        if (!$questionnaire) {
            return;
        }
        $audience = $this->normalizeAudience($questionnaire->audience ?? null);
        Cache::forget("questionnaire:active:{$audience}");
        Cache::forget("questionnaire:{$questionnaire->id}:question_ids");
    }

    protected function applyActiveFlag(Questionnaire $questionnaire): void
    {
        if ($questionnaire->is_active) {
            $audience = $this->normalizeAudience($questionnaire->audience);
            Questionnaire::where('audience', $audience)
                ->where('id', '!=', $questionnaire->id)
                ->update(['is_active' => false]);
        }
    }
}
