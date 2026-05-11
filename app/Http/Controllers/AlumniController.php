<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlumniRequest;
use App\Http\Requests\UpdateAlumniRequest;
use App\Http\Resources\AlumniResource;
use App\Models\Alumni;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogger;
use App\Services\AlumniImportProgress;

class AlumniController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Alumni::class);

        $query = $this->applyUserScope(Alumni::query())
            ->when(request('nim'), fn($q) => $q->where('nim', request('nim')))
            ->when(request('email'), fn($q) => $q->where('email', request('email')))
            ->latest();

        $perPageParam = request('per_page');
        if (is_string($perPageParam) && strtolower($perPageParam) === 'all') {
            $records = $query->get();
            return AlumniResource::collection($records)->additional([
                'meta' => ['total' => $records->count()],
                'links' => ['self' => request()->fullUrl()],
            ]);
        }

        $perPage = (int) request('per_page', 50);
        $perPage = max(5, min($perPage, 200));

        $alumni = $query->paginate($perPage);

        return AlumniResource::collection($alumni);
    }

    public function store(StoreAlumniRequest $request)
    {
        $this->authorize('create', Alumni::class);

        $alumni = Alumni::create($request->validated());

        AuditLogger::log('alumni.created', 'alumni', $alumni->id);

        return new AlumniResource($alumni);
    }

    public function show($alumni)
    {
        $record = Alumni::query()
            ->where('id', $alumni)
            ->orWhere('nim', $alumni)
            ->firstOrFail();

        $this->authorize('view', $record);

        return new AlumniResource($record);
    }

    public function update(UpdateAlumniRequest $request, Alumni $alumni)
    {
        $this->authorize('update', $alumni);

        $alumni->update($request->validated());

        AuditLogger::log('alumni.updated', 'alumni', $alumni->id);

        return new AlumniResource($alumni);
    }

    public function destroy(Alumni $alumni)
    {
        $this->authorize('delete', $alumni);

        $alumni->delete();

        AuditLogger::log('alumni.deleted', 'alumni', $alumni->id);

        return response()->noContent();
    }

    /**
     * Import data alumni dari file CSV.
     */
    /**
     * Import data alumni dari file CSV (Queue).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required_without:alumni_csv', 'file', 'mimes:csv,txt', 'max:10240'],
            'alumni_csv' => ['required_without:file', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $uploadedFile = $request->file('file') ?? $request->file('alumni_csv');

        // Save file to storage so Job can read it
        $path = $uploadedFile->store('imports');
        $fullPath = Storage::path($path);
        $importId = (string) Str::uuid();
        $totalRows = $this->countCsvDataRows($fullPath);

        $forceSync = app()->environment('local') || config('queue.default') === 'sync';

        AlumniImportProgress::put($importId, [
            'status' => 'queued',
            'message' => 'File diterima server. Menunggu proses import...',
            'percentage' => 0,
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'user_id' => auth()->id(),
        ]);

        if ($forceSync) {
            $summary = (new \App\Jobs\ImportAlumniJob($fullPath, auth()->id(), $importId, $totalRows))->handle();

            AuditLogger::log('alumni.import_completed', 'alumni', null, [
                'mode' => 'sync',
            ]);

            return response()->json([
                'message' => 'Import selesai diproses.',
                'job_status' => 'done',
                'import_id' => $importId,
                'summary' => $summary,
            ]);
        }

        // Dispatch Job (async)
        \App\Jobs\ImportAlumniJob::dispatch($fullPath, auth()->id(), $importId, $totalRows);

        AuditLogger::log('alumni.import_requested', 'alumni', null, [
            'mode' => 'queued',
        ]);

        return response()->json([
            'message' => 'Import sedang berjalan di latar belakang. Jalankan worker queue agar data masuk.',
            'job_status' => 'queued',
            'import_id' => $importId,
            'summary' => [
                'status' => 'queued',
                'percentage' => 0,
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'success_count' => 0,
                'error_count' => 0,
            ],
        ]);
    }

    public function importProgress(string $importId)
    {
        $progress = AlumniImportProgress::get($importId);

        if (!$progress) {
            return response()->json([
                'message' => 'Status import tidak ditemukan.',
                'status' => 'not_found',
                'percentage' => 0,
            ], 404);
        }

        return response()->json($progress);
    }

    /**
     * Lookup khusus berdasarkan NIM (dipakai frontend untuk autofill kuisioner).
     */
    public function lookupByNim(string $nim)
    {
        $nim = trim($nim);
        $validator = \Illuminate\Support\Facades\Validator::make(['nim' => $nim], [
            'nim' => ['required', 'string', 'alpha_num', 'max:32'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $record = Alumni::where('nim', $nim)->first();
        if (! $record) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        return new \App\Http\Resources\PublicAlumniResource($record);
    }

    protected function mapCsvHeaders(array $headers): array
    {
        return array_map(function (?string $raw) {
            $normalized = $this->normalizeHeader($raw);
            return $normalized ? $this->mapImportColumn($normalized) : null;
        }, $headers);
    }

    protected function normalizeHeader(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = Str::of($value)->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return (string) $value;
    }

    protected function mapImportColumn(string $column): ?string
    {
        $map = [
            'nama' => 'nama',
            'name' => 'nama',
            'full_name' => 'nama',
            'nim' => 'nim',
            'nik' => 'nik',
            'prodi' => 'prodi',
            'program_studi' => 'prodi',
            'study_program' => 'prodi',
            'fakultas' => 'fakultas',
            'faculty' => 'fakultas',
            'tahun_lulus' => 'tahun_lulus',
            'graduation_year' => 'tahun_lulus',
            'year_graduated' => 'tahun_lulus',
            'tahun_masuk' => 'tahun_masuk',
            'entry_year' => 'tahun_masuk',
            'year_admitted' => 'tahun_masuk',
            'email' => 'email',
            'email_address' => 'email',
            'mail' => 'email',
            'no_hp' => 'no_hp',
            'hp' => 'no_hp',
            'phone' => 'no_hp',
            'phone_number' => 'no_hp',
            'alamat' => 'alamat',
            'address' => 'alamat',
            'tanggal_lahir' => 'tanggal_lahir',
            'birth_date' => 'tanggal_lahir',
            'dob' => 'tanggal_lahir',
            'foto' => 'foto',
            'photo' => 'foto',
            'sent' => 'sent',
            'status_pekerjaan' => 'status_pekerjaan',
            'job_status' => 'status_pekerjaan',
        ];

        return $map[$column] ?? null;
    }

    protected function readCsvRow($handle): ?array
    {
        while (!feof($handle)) {
            $row = fgetcsv($handle);
            if ($row === false) {
                return null;
            }

            if (count($row) === 1 && $row[0] === null) {
                continue;
            }

            return $row;
        }

        return null;
    }

    protected function mapCsvRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($row as $index => $value) {
            $column = $headers[$index] ?? null;
            if (!$column) {
                continue;
            }

            $mapped[$column] = $value;
        }

        return $mapped;
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function normalizeImportRecord(array $row): array
    {
        $nim = $this->sanitizeValue($row['nim'] ?? null);
        $email = $this->sanitizeValue($row['email'] ?? null);

        if (!$email && $nim) {
            $email = strtolower(preg_replace('/[^a-z0-9]/', '', $nim)) . '@import.local';
        }

        return [
            'nama' => $this->sanitizeValue($row['nama'] ?? null),
            'nim' => $nim,
            'nik' => $this->sanitizeValue($row['nik'] ?? null),
            'prodi' => $this->sanitizeValue($row['prodi'] ?? null),
            'fakultas' => $this->sanitizeValue($row['fakultas'] ?? null),
            'tahun_masuk' => $this->castYear($this->sanitizeValue($row['tahun_masuk'] ?? null)),
            'tahun_lulus' => $this->castYear($this->sanitizeValue($row['tahun_lulus'] ?? null)),
            'email' => $email,
            'no_hp' => $this->sanitizeValue($row['no_hp'] ?? null),
            'alamat' => $this->sanitizeValue($row['alamat'] ?? null),
            'tanggal_lahir' => $this->sanitizeValue($row['tanggal_lahir'] ?? null),
            'foto' => $this->sanitizeValue($row['foto'] ?? null),
            'sent' => $this->castBoolean($row['sent'] ?? null),
            'status_pekerjaan' => $this->sanitizeValue($row['status_pekerjaan'] ?? null),
        ];
    }

    protected function sanitizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        return $normalized === '' ? null : $normalized;
    }

    protected function castYear(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);
        if (strlen($digits) !== 4) {
            return null;
        }

        return (int) $digits;
    }

    protected function castBoolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    protected function requiredImportFields(): array
    {
        return ['nama', 'nim', 'prodi', 'tahun_lulus'];
    }

    protected function countCsvDataRows(string $fullPath): int
    {
        $handle = fopen($fullPath, 'r');
        if (!$handle) {
            return 0;
        }

        $rows = 0;
        $isHeader = true;

        while (($row = fgetcsv($handle, 2000, ",")) !== false) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            $isEmpty = true;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $isEmpty = false;
                    break;
                }
            }

            if (!$isEmpty) {
                $rows++;
            }
        }

        fclose($handle);
        return $rows;
    }

    protected function applyUserScope($query)
    {
        $user = Auth::user();
        if (! $user) {
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
                return $query->where(function ($inner) use ($variants) {
                    foreach ($variants as $index => $value) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $inner->$method('LOWER(TRIM(fakultas)) = ?', [$value]);
                    }
                });
            }
            return $query->whereRaw('1=0');
        }

        if ($roleName === 'Admin Prodi') {
            $prodi = trim((string) ($user->prodi ?? ''));
            if ($prodi !== '') {
                $normalized = $this->normalizeUnit($prodi);
                return $query->whereRaw('LOWER(TRIM(prodi)) = ?', [$normalized]);
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
