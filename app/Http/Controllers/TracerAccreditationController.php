<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use App\Services\TracerAccreditationSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class TracerAccreditationController extends Controller
{
    public function __construct(
        protected TracerAccreditationSummaryService $summaryService
    ) {
    }

    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'questionnaire_id' => ['nullable', 'integer', 'min:1'],
            'accreditation_year' => ['nullable', 'integer', 'between:2000,2100'],
            'fakultas' => ['nullable', 'string', 'max:120'],
            'prodi' => ['nullable', 'string', 'max:120'],
            'ts_labels' => ['nullable'],
        ]);
        $validator->validate();

        $questionnaireId = (int) ($request->query('questionnaire_id') ?? 0);
        $accreditationYear = (int) ($request->query('accreditation_year') ?? now()->year);
        $fakultas = trim((string) ($request->query('fakultas') ?? 'all'));
        $prodi = trim((string) ($request->query('prodi') ?? 'all'));
        $tsLabels = $this->parseTsLabels($request->query('ts_labels'));

        $questionnaire = $this->resolveQuestionnaire($questionnaireId);
        if (!$questionnaire) {
            $empty = $this->summaryService->emptyPayload(
                0,
                '',
                [
                    'accreditation_year' => $accreditationYear,
                    'fakultas' => $fakultas ?: 'all',
                    'prodi' => $prodi ?: 'all',
                    'ts_labels' => $tsLabels,
                ],
                $this->summaryService->buildFilterOptions($this->resolveRoleScope($request))
            );

            return response()->json($empty);
        }

        $roleScope = $this->resolveRoleScope($request);
        $effectiveScope = $this->resolveEffectiveScope(
            $accreditationYear,
            $fakultas ?: 'all',
            $prodi ?: 'all',
            $tsLabels,
            $roleScope
        );

        if ($effectiveScope['forced_empty']) {
            $empty = $this->summaryService->emptyPayload(
                $questionnaire->id,
                (string) $questionnaire->judul,
                $effectiveScope,
                $this->summaryService->buildFilterOptions($roleScope)
            );
            return response()->json($empty);
        }

        $versionKey = "dashboard:tracer:accreditation:version:{$questionnaire->id}";
        $cacheVersion = (int) Cache::get($versionKey, 1);
        $cacheKey = implode(':', [
            'dashboard',
            'tracer',
            'accreditation-summary',
            'q' . $questionnaire->id,
            'v' . $cacheVersion,
            'y' . $effectiveScope['accreditation_year'],
            'f' . md5($effectiveScope['fakultas']),
            'p' . md5($effectiveScope['prodi']),
            'ts' . md5(implode(',', $effectiveScope['ts_labels'])),
            'role' . md5(json_encode($roleScope)),
        ]);

        $ttlSeconds = max(60, (int) env('TRACER_ACCREDITATION_CACHE_TTL', 300));
        $payload = Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($questionnaire, $effectiveScope, $roleScope) {
            return $this->summaryService->buildSummary(
                (int) $questionnaire->id,
                (string) $questionnaire->judul,
                $effectiveScope,
                $roleScope
            );
        });

        return response()->json($payload);
    }

    protected function parseTsLabels($raw): array
    {
        $values = is_array($raw)
            ? $raw
            : explode(',', (string) ($raw ?? 'TS-1,TS-2'));

        $allowed = ['TS', 'TS-1', 'TS-2', 'TS-3', 'TS-4', 'TS-5'];
        $selected = [];
        foreach ($allowed as $label) {
            if (in_array($label, array_map('trim', $values), true)) {
                $selected[] = $label;
            }
        }

        return $selected ?: ['TS-1', 'TS-2'];
    }

    protected function resolveQuestionnaire(int $questionnaireId): ?Questionnaire
    {
        if ($questionnaireId > 0) {
            $explicit = Questionnaire::find($questionnaireId);
            if ($explicit && strtolower((string) $explicit->audience) === 'alumni') {
                return $explicit;
            }
        }

        return Questionnaire::query()
            ->where('audience', 'alumni')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();
    }

    protected function resolveRoleScope(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        $user->loadMissing('role');
        $roleName = $user->role->nama_role ?? $user->role ?? '';
        $roleSlug = $this->slugify((string) $roleName);

        $scope = [
            'role' => $roleSlug,
        ];

        if ($roleSlug === 'admin_fakultas') {
            $scope['fakultas'] = $this->normalizeUnit((string) ($user->fakultas ?? ''));
        }

        if ($roleSlug === 'admin_prodi') {
            $scope['prodi'] = $this->normalizeUnit((string) ($user->prodi ?? ''));
            $scope['fakultas'] = $this->normalizeUnit((string) ($user->fakultas ?? ''));
        }

        return array_filter($scope, fn($value) => $value !== '');
    }

    protected function resolveEffectiveScope(
        int $accreditationYear,
        string $fakultas,
        string $prodi,
        array $tsLabels,
        array $roleScope
    ): array {
        $forcedEmpty = false;
        $effectiveFakultas = $fakultas;
        $effectiveProdi = $prodi;

        $roleFakultas = $roleScope['fakultas'] ?? null;
        if ($roleFakultas) {
            if ($this->isFiltered($fakultas) && $this->normalizeUnit($fakultas) !== $roleFakultas) {
                $forcedEmpty = true;
            }
            $effectiveFakultas = $roleFakultas;
        }

        $roleProdi = $roleScope['prodi'] ?? null;
        if ($roleProdi) {
            if ($this->isFiltered($prodi) && $this->normalizeUnit($prodi) !== $roleProdi) {
                $forcedEmpty = true;
            }
            $effectiveProdi = $roleProdi;
        }

        return [
            'accreditation_year' => $accreditationYear,
            'fakultas' => $effectiveFakultas ?: 'all',
            'prodi' => $effectiveProdi ?: 'all',
            'ts_labels' => $tsLabels,
            'forced_empty' => $forcedEmpty,
        ];
    }

    protected function isFiltered(?string $value): bool
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' && strtolower($text) !== 'all';
    }

    protected function normalizeUnit(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    protected function slugify(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }
}
