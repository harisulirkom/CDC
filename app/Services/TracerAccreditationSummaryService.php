<?php

namespace App\Services;

use App\Models\Alumni;
use App\Models\Response;
use Illuminate\Support\Collection;

class TracerAccreditationSummaryService
{
    protected const TS_LABEL_OFFSETS = [
        'TS' => 0,
        'TS-1' => 1,
        'TS-2' => 2,
        'TS-3' => 3,
        'TS-4' => 4,
        'TS-5' => 5,
    ];

    protected const TS_ORDER = ['TS', 'TS-1', 'TS-2', 'TS-3', 'TS-4', 'TS-5'];

    public function buildSummary(int $questionnaireId, string $questionnaireTitle, array $scope, array $roleScope = []): array
    {
        $accreditationYear = $scope['accreditation_year'];
        $tsLabels = $this->normalizeTsLabels($scope['ts_labels'] ?? []);
        $fakultasFilter = $scope['fakultas'] ?? 'all';
        $prodiFilter = $scope['prodi'] ?? 'all';

        $filterOptions = $this->buildFilterOptions($roleScope);
        if (empty($tsLabels)) {
            return $this->emptyPayload($questionnaireId, $questionnaireTitle, $scope, $filterOptions);
        }

        $eligibleAlumni = $this->fetchEligibleAlumni($accreditationYear, $tsLabels, $fakultasFilter, $prodiFilter, $roleScope);

        $eligibleByNim = [];
        $eligibleByTs = [];
        foreach ($tsLabels as $label) {
            $eligibleByTs[$label] = [];
        }

        foreach ($eligibleAlumni as $alumni) {
            $nim = $this->normalizeNim($alumni->nim);
            if ($nim === '') {
                continue;
            }
            $tsLabel = $this->resolveTsLabel((int) $alumni->tahun_lulus, $accreditationYear);
            if (!in_array($tsLabel, $tsLabels, true)) {
                continue;
            }

            $entry = [
                'id' => $alumni->id,
                'nim' => $nim,
                'nama' => $this->safeText($alumni->nama, '-'),
                'fakultas' => $this->safeText($alumni->fakultas, '-'),
                'prodi' => $this->safeText($alumni->prodi, '-'),
                'tahunLulus' => (int) $alumni->tahun_lulus,
                'status' => $this->safeText($alumni->status_pekerjaan, '-'),
                'tsLabel' => $tsLabel,
            ];

            $eligibleByNim[$nim] = $entry;
            $eligibleByTs[$tsLabel][$nim] = $entry;
        }

        $matchedByTs = [];
        $unmatchedByTs = [];
        $respondentSetByTs = [];
        foreach ($tsLabels as $label) {
            $matchedByTs[$label] = [];
            $unmatchedByTs[$label] = [];
            $respondentSetByTs[$label] = [];
        }

        $rawResponses = 0;
        $unmatchedResponses = 0;

        $responseQuery = Response::query()
            ->from('responses')
            ->leftJoin('alumnis', 'alumnis.id', '=', 'responses.alumni_id')
            ->where('responses.questionnaire_id', $questionnaireId)
            ->select([
                'responses.id',
                'responses.attempt_ke',
                'responses.created_at',
                'responses.form_data',
                'alumnis.nim as alumni_nim',
                'alumnis.nama as alumni_nama',
                'alumnis.fakultas as alumni_fakultas',
                'alumnis.prodi as alumni_prodi',
                'alumnis.tahun_lulus as alumni_tahun_lulus',
                'alumnis.status_pekerjaan as alumni_status',
            ])
            ->orderBy('responses.id');

        $responseQuery->chunkById(1000, function (Collection $rows) use (
            $accreditationYear,
            $tsLabels,
            $fakultasFilter,
            $prodiFilter,
            $eligibleByNim,
            &$matchedByTs,
            &$unmatchedByTs,
            &$respondentSetByTs,
            &$rawResponses,
            &$unmatchedResponses
        ) {
            foreach ($rows as $row) {
                $formData = $this->toArray($row->form_data);
                $nim = $this->normalizeNim($row->alumni_nim ?: ($formData['nim'] ?? null));
                $matchedAlumni = $nim !== '' ? ($eligibleByNim[$nim] ?? null) : null;

                if ($matchedAlumni) {
                    $label = $matchedAlumni['tsLabel'];
                    if (!isset($matchedByTs[$label])) {
                        continue;
                    }

                    $rawResponses++;
                    $respondentSetByTs[$label][$nim] = true;

                    if (!isset($matchedByTs[$label][$nim])) {
                        $matchedByTs[$label][$nim] = [
                            'id' => $nim,
                            'nim' => $matchedAlumni['nim'],
                            'nama' => $matchedAlumni['nama'],
                            'fakultas' => $matchedAlumni['fakultas'],
                            'prodi' => $matchedAlumni['prodi'],
                            'tahunLulus' => $matchedAlumni['tahunLulus'],
                            'status' => $this->safeText($row->alumni_status, $matchedAlumni['status']),
                            'lastSubmittedAt' => $this->toIsoTimestamp($row->created_at),
                            'attemptCount' => 0,
                        ];
                    }

                    $matchedByTs[$label][$nim]['attemptCount'] += max(1, (int) ($row->attempt_ke ?? 1));
                    $currentSubmitted = $this->toIsoTimestamp($row->created_at);
                    $previousSubmitted = $matchedByTs[$label][$nim]['lastSubmittedAt'] ?? null;
                    if ($currentSubmitted && (!$previousSubmitted || $currentSubmitted > $previousSubmitted)) {
                        $matchedByTs[$label][$nim]['lastSubmittedAt'] = $currentSubmitted;
                    }
                    continue;
                }

                $responseYear = $this->extractResponseYear($formData);
                $tsLabel = $this->resolveTsLabel($responseYear, $accreditationYear);
                if (!in_array($tsLabel, $tsLabels, true)) {
                    continue;
                }

                $responseFakultas = $this->safeText($formData['fakultas'] ?? null, '-');
                $responseProdi = $this->safeText($formData['prodi'] ?? null, '-');
                if (!$this->matchFilter($responseFakultas, $fakultasFilter)) {
                    continue;
                }
                if (!$this->matchFilter($responseProdi, $prodiFilter)) {
                    continue;
                }

                $rawResponses++;
                $unmatchedResponses++;
                $unmatchedByTs[$tsLabel][] = [
                    'id' => $row->id,
                    'nim' => $nim,
                    'nama' => $this->safeText($formData['nama'] ?? ($formData['nama_alumni'] ?? null), '-'),
                    'fakultas' => $responseFakultas,
                    'prodi' => $responseProdi,
                    'tahunLulus' => $responseYear ?: '-',
                    'status' => $this->safeText($formData['status'] ?? null, '-'),
                    'lastSubmittedAt' => $this->toIsoTimestamp($row->created_at),
                    'attemptCount' => max(1, (int) ($row->attempt_ke ?? 1)),
                ];
            }
        }, 'responses.id', 'id');

        $cohortRows = [];
        $detailByTs = [];
        $totalEligible = 0;
        $totalRespondents = 0;

        foreach ($tsLabels as $label) {
            $eligibleBucket = $eligibleByTs[$label] ?? [];
            $matchedBucket = $matchedByTs[$label] ?? [];
            $respondentBucket = $respondentSetByTs[$label] ?? [];

            $totalAlumni = count($eligibleBucket);
            $respondents = count($respondentBucket);
            $responseRate = $totalAlumni > 0
                ? round(($respondents / $totalAlumni) * 100, 2)
                : 0.0;

            $cohortRows[] = [
                'tsLabel' => $label,
                'totalAlumni' => $totalAlumni,
                'respondents' => $respondents,
                'responseRate' => $responseRate,
            ];

            $alreadyRows = array_values($matchedBucket);
            usort($alreadyRows, function (array $a, array $b) {
                return strcmp($a['nim'] ?? '', $b['nim'] ?? '');
            });

            $pendingRows = [];
            foreach ($eligibleBucket as $nim => $entry) {
                if (isset($respondentBucket[$nim])) {
                    continue;
                }
                $pendingRows[] = [
                    'id' => 'pending-' . $nim,
                    'nim' => $entry['nim'],
                    'nama' => $entry['nama'],
                    'fakultas' => $entry['fakultas'],
                    'prodi' => $entry['prodi'],
                    'tahunLulus' => $entry['tahunLulus'],
                    'status' => $entry['status'],
                    'lastSubmittedAt' => null,
                    'attemptCount' => 0,
                ];
            }
            usort($pendingRows, function (array $a, array $b) {
                return strcmp($a['nim'] ?? '', $b['nim'] ?? '');
            });

            $unmatchedRows = $unmatchedByTs[$label] ?? [];

            $detailByTs[$label] = [
                'already' => $alreadyRows,
                'pending' => $pendingRows,
                'unmatched' => $unmatchedRows,
            ];

            $totalEligible += $totalAlumni;
            $totalRespondents += $respondents;
        }

        $overallRate = $totalEligible > 0
            ? round(($totalRespondents / $totalEligible) * 100, 2)
            : 0.0;

        return [
            'totalRespondents' => $totalRespondents,
            'scope' => [
                'accreditationYear' => $accreditationYear,
                'fakultas' => $fakultasFilter,
                'prodi' => $prodiFilter,
                'tsLabels' => $tsLabels,
            ],
            'summary' => [
                'totalAlumni' => $totalEligible,
                'totalRespondents' => $totalRespondents,
                'responseRate' => $overallRate,
                'rawResponses' => $rawResponses,
                'unmatchedResponses' => $unmatchedResponses,
            ],
            'cohortRows' => $cohortRows,
            'detailByTs' => $detailByTs,
            'filterOptions' => $filterOptions,
            'source' => [
                'questionnaireId' => $questionnaireId,
                'questionnaireTitle' => $questionnaireTitle,
                'generatedAt' => now()->toIso8601String(),
                'responseCount' => $rawResponses,
            ],
        ];
    }

    public function emptyPayload(int $questionnaireId, string $questionnaireTitle, array $scope, array $filterOptions): array
    {
        $tsLabels = $this->normalizeTsLabels($scope['ts_labels'] ?? []);
        $cohortRows = [];
        $detailByTs = [];
        foreach ($tsLabels as $label) {
            $cohortRows[] = [
                'tsLabel' => $label,
                'totalAlumni' => 0,
                'respondents' => 0,
                'responseRate' => 0.0,
            ];
            $detailByTs[$label] = [
                'already' => [],
                'pending' => [],
                'unmatched' => [],
            ];
        }

        return [
            'totalRespondents' => 0,
            'scope' => [
                'accreditationYear' => (int) ($scope['accreditation_year'] ?? now()->year),
                'fakultas' => $scope['fakultas'] ?? 'all',
                'prodi' => $scope['prodi'] ?? 'all',
                'tsLabels' => $tsLabels,
            ],
            'summary' => [
                'totalAlumni' => 0,
                'totalRespondents' => 0,
                'responseRate' => 0.0,
                'rawResponses' => 0,
                'unmatchedResponses' => 0,
            ],
            'cohortRows' => $cohortRows,
            'detailByTs' => $detailByTs,
            'filterOptions' => $filterOptions,
            'source' => [
                'questionnaireId' => $questionnaireId,
                'questionnaireTitle' => $questionnaireTitle,
                'generatedAt' => now()->toIso8601String(),
                'responseCount' => 0,
            ],
        ];
    }

    protected function fetchEligibleAlumni(
        int $accreditationYear,
        array $tsLabels,
        string $fakultasFilter,
        string $prodiFilter,
        array $roleScope
    ): Collection {
        $years = [];
        foreach ($tsLabels as $label) {
            $offset = self::TS_LABEL_OFFSETS[$label] ?? null;
            if ($offset === null) {
                continue;
            }
            $years[] = $accreditationYear - $offset;
        }
        $years = array_values(array_unique($years));

        if (empty($years)) {
            return collect();
        }

        $query = Alumni::query()
            ->select(['id', 'nim', 'nama', 'fakultas', 'prodi', 'tahun_lulus', 'status_pekerjaan'])
            ->whereIn('tahun_lulus', $years);

        $roleFakultas = $roleScope['fakultas'] ?? null;
        if ($roleFakultas) {
            $query->whereRaw('LOWER(TRIM(COALESCE(fakultas, ""))) = ?', [$this->normalizeComparable($roleFakultas)]);
        }

        $roleProdi = $roleScope['prodi'] ?? null;
        if ($roleProdi) {
            $query->whereRaw('LOWER(TRIM(COALESCE(prodi, ""))) = ?', [$this->normalizeComparable($roleProdi)]);
        }

        if ($this->isFiltered($fakultasFilter)) {
            $query->whereRaw('LOWER(TRIM(COALESCE(fakultas, ""))) = ?', [$this->normalizeComparable($fakultasFilter)]);
        }

        if ($this->isFiltered($prodiFilter)) {
            $query->whereRaw('LOWER(TRIM(COALESCE(prodi, ""))) = ?', [$this->normalizeComparable($prodiFilter)]);
        }

        return $query->get();
    }

    public function buildFilterOptions(array $roleScope): array
    {
        $query = Alumni::query()
            ->select(['fakultas', 'prodi'])
            ->whereNotNull('fakultas')
            ->whereNotNull('prodi');

        $roleFakultas = $roleScope['fakultas'] ?? null;
        if ($roleFakultas) {
            $query->whereRaw('LOWER(TRIM(COALESCE(fakultas, ""))) = ?', [$this->normalizeComparable($roleFakultas)]);
        }
        $roleProdi = $roleScope['prodi'] ?? null;
        if ($roleProdi) {
            $query->whereRaw('LOWER(TRIM(COALESCE(prodi, ""))) = ?', [$this->normalizeComparable($roleProdi)]);
        }

        $rows = $query->get();
        $fakultasList = [];
        $prodiList = [];
        $prodiByFakultas = [];

        foreach ($rows as $row) {
            $fakultas = $this->safeText($row->fakultas, '');
            $prodi = $this->safeText($row->prodi, '');
            if ($fakultas === '' || $prodi === '') {
                continue;
            }
            $fakultasList[$fakultas] = true;
            $prodiList[$prodi] = true;
            if (!isset($prodiByFakultas[$fakultas])) {
                $prodiByFakultas[$fakultas] = [];
            }
            $prodiByFakultas[$fakultas][$prodi] = true;
        }

        $fakultasValues = array_keys($fakultasList);
        sort($fakultasValues, SORT_NATURAL | SORT_FLAG_CASE);

        $prodiValues = array_keys($prodiList);
        sort($prodiValues, SORT_NATURAL | SORT_FLAG_CASE);

        $normalizedByFakultas = [];
        foreach ($prodiByFakultas as $fakultas => $map) {
            $values = array_keys($map);
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);
            $normalizedByFakultas[$fakultas] = $values;
        }

        return [
            'fakultas' => $fakultasValues,
            'prodi' => $prodiValues,
            'prodiByFakultas' => $normalizedByFakultas,
        ];
    }

    protected function normalizeTsLabels(array $labels): array
    {
        $normalized = [];
        foreach (self::TS_ORDER as $label) {
            if (in_array($label, $labels, true)) {
                $normalized[] = $label;
            }
        }
        return $normalized;
    }

    protected function resolveTsLabel(?int $graduationYear, int $accreditationYear): string
    {
        if (!$graduationYear || $graduationYear < 1900) {
            return '';
        }
        $diff = $accreditationYear - $graduationYear;
        if ($diff < 0 || $diff > 5) {
            return '';
        }
        return $diff === 0 ? 'TS' : 'TS-' . $diff;
    }

    protected function extractResponseYear(array $formData): ?int
    {
        $keys = ['tahunLulus', 'tahun_lulus', 'tahun'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $formData)) {
                continue;
            }
            $value = $formData[$key];
            if ($value === null || $value === '') {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (!$digits) {
                continue;
            }
            $year = (int) $digits;
            if ($year >= 1990 && $year <= 2100) {
                return $year;
            }
        }
        return null;
    }

    protected function matchFilter(string $value, string $filter): bool
    {
        if (!$this->isFiltered($filter)) {
            return true;
        }
        return $this->normalizeComparable($value) === $this->normalizeComparable($filter);
    }

    protected function isFiltered(?string $value): bool
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' && strtolower($text) !== 'all';
    }

    protected function normalizeComparable(?string $value): string
    {
        $text = strtolower(trim((string) ($value ?? '')));
        return preg_replace('/\s+/', ' ', $text) ?? '';
    }

    protected function normalizeNim(?string $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }

    protected function safeText($value, string $fallback = '-'): string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? $fallback : $text;
    }

    protected function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function toIsoTimestamp($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
