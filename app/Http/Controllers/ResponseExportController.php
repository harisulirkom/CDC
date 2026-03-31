<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateResponseExport;
use App\Models\ExportJob;
use App\Models\Questionnaire;
use App\Models\Response;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResponseExportController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('viewAny', Response::class);

        $data = $request->validate([
            'questionnaire_id' => ['required', 'integer', 'exists:questionnaires,id'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
            'filters' => ['nullable', 'array'],
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.fakultas' => ['nullable', 'string', 'max:100'],
            'filters.prodi' => ['nullable', 'string', 'max:100'],
            'filters.tahun' => ['nullable', 'digits:4'],
            'filters.status' => ['nullable', 'array'],
            'filters.status.*' => ['string', 'max:30'],
            'filters.question_id' => ['nullable', 'integer'],
            'filters.answer_value' => ['nullable', 'string', 'max:100'],
        ]);

        $questionnaire = Questionnaire::findOrFail($data['questionnaire_id']);

        $format = ($data['format'] ?? 'csv') === 'xlsx' ? 'csv' : ($data['format'] ?? 'csv');
        $filters = $this->sanitizeFilters($data['filters'] ?? []);

        $job = ExportJob::create([
            'questionnaire_id' => $questionnaire->id,
            'status' => 'queued',
            'format' => $format,
            'filters' => $filters,
            'requested_by' => Auth::id(),
        ]);

        AuditLogger::log('export.requested', 'export_job', $job->id, [
            'questionnaire_id' => $questionnaire->id,
            'format' => $format,
        ]);

        GenerateResponseExport::dispatch($job->id);

        return response()->json([
            'export_id' => $job->id,
            'status' => $job->status,
        ], 202);
    }

    public function show(ExportJob $export)
    {
        if (! $this->canAccessExport($export)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'export_id' => $export->id,
            'status' => $export->status,
            'file_path' => $export->status === 'ready' ? $export->file_path : null,
            'error_message' => $export->error_message,
            'updated_at' => $export->updated_at?->toIso8601String(),
        ]);
    }

    public function download(ExportJob $export)
    {
        if (! $this->canAccessExport($export)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($export->status !== 'ready' || !$export->file_path) {
            return response()->json(['message' => 'Export belum siap.'], 409);
        }

        if (!file_exists($export->file_path)) {
            return response()->json(['message' => 'File export tidak ditemukan.'], 404);
        }

        AuditLogger::log('export.downloaded', 'export_job', $export->id, [
            'questionnaire_id' => $export->questionnaire_id,
        ]);

        return response()->download($export->file_path);
    }

    protected function canAccessExport(ExportJob $export): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $user->loadMissing('role');
        $roleName = $user->role->nama_role ?? $user->role ?? '';
        $roleSlug = $this->slugify((string) $roleName);
        $isPrivileged = in_array($roleSlug, ['super_admin', 'admin_universitas'], true);

        return $isPrivileged || (int) $export->requested_by === (int) $user->id;
    }

    protected function slugify(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }

    protected function sanitizeFilters(array $filters): array
    {
        $allowed = ['search', 'fakultas', 'prodi', 'tahun', 'status', 'question_id', 'answer_value'];
        $clean = array_intersect_key($filters, array_flip($allowed));

        $clean['search'] = isset($clean['search']) ? mb_substr(trim((string) $clean['search']), 0, 100) : '';
        $clean['fakultas'] = isset($clean['fakultas']) ? mb_substr(trim((string) $clean['fakultas']), 0, 100) : '';
        $clean['prodi'] = isset($clean['prodi']) ? mb_substr(trim((string) $clean['prodi']), 0, 100) : '';
        $clean['tahun'] = isset($clean['tahun']) ? mb_substr(trim((string) $clean['tahun']), 0, 4) : '';
        $clean['answer_value'] = isset($clean['answer_value']) ? mb_substr(trim((string) $clean['answer_value']), 0, 100) : '';

        if (isset($clean['question_id'])) {
            $clean['question_id'] = (int) $clean['question_id'];
        }

        if (isset($clean['status']) && is_array($clean['status'])) {
            $clean['status'] = array_values(array_filter(array_map('trim', $clean['status'])));
        }

        return $clean;
    }
}
