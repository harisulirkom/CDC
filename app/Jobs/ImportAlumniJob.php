<?php

namespace App\Jobs;

use App\Models\Alumni;
use App\Services\AlumniImportParser;
use App\Services\AlumniImportProgress;
use App\Services\AlumniImportReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportAlumniJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $filePath,
        protected ?int $userId = null,
        protected ?string $importId = null,
        protected ?int $totalRows = null,
        protected string $mode = AlumniImportParser::MODE_SMART,
    ) {
        $this->importId = $this->importId ?: (string) Str::uuid();
        $this->totalRows = max(0, (int) ($this->totalRows ?? 0));
        $this->mode = in_array($this->mode, [AlumniImportParser::MODE_SMART, AlumniImportParser::MODE_STRICT], true)
            ? $this->mode
            : AlumniImportParser::MODE_SMART;
    }

    /** @return array<string, mixed> */
    public function handle(): array
    {
        if (!file_exists($this->filePath)) {
            $summary = $this->buildSummary(
                status: 'failed',
                processedRows: 0,
                successCount: 0,
                errorCount: 0,
                message: 'File import tidak ditemukan.',
            );
            AlumniImportProgress::put($this->importId, $summary);
            return $summary;
        }

        $processedRows = 0;
        $successCount = 0;
        $errorCount = 0;
        $lastProgressWrite = 0;
        $failedRows = [];
        $parseMeta = [
            'format' => 'csv',
            'delimiter' => ',',
            'encoding' => 'UTF-8',
        ];

        AlumniImportProgress::put($this->importId, $this->buildSummary(
            status: 'processing',
            processedRows: 0,
            successCount: 0,
            errorCount: 0,
            message: 'Memulai proses import...',
        ));

        try {
            /** @var AlumniImportParser $parser */
            $parser = app(AlumniImportParser::class);
            $parsed = $parser->parseFile($this->filePath, $this->mode, true);
            $parseMeta = [
                'format' => (string) ($parsed['format'] ?? 'csv'),
                'delimiter' => (string) ($parsed['delimiter'] ?? ','),
                'encoding' => (string) ($parsed['encoding'] ?? 'UTF-8'),
            ];

            $missingHeaders = $parsed['missing_required_headers'] ?? [];
            if (!empty($missingHeaders)) {
                $summary = $this->buildSummary(
                    status: 'failed',
                    processedRows: 0,
                    successCount: 0,
                    errorCount: 0,
                    message: 'Header wajib tidak ditemukan: ' . implode(', ', $missingHeaders),
                    parseMeta: $parseMeta,
                );
                AlumniImportProgress::put($this->importId, $summary);
                return $summary;
            }

            $records = is_array($parsed['records'] ?? null) ? $parsed['records'] : [];
            $this->totalRows = max($this->totalRows, (int) ($parsed['total_rows'] ?? count($records)));

            foreach ($records as $record) {
                $processedRows++;
                $rowNumber = (int) ($record['row_number'] ?? $processedRows + 1);
                $data = is_array($record['data'] ?? null) ? $record['data'] : [];
                $recordErrors = is_array($record['errors'] ?? null) ? $record['errors'] : [];

                if (!empty($recordErrors)) {
                    $errorCount++;
                    $failedRows[] = [
                        'row_number' => $rowNumber,
                        'nim' => (string) ($data['nim'] ?? ''),
                        'nama' => (string) ($data['nama'] ?? ''),
                        'email' => (string) ($data['email'] ?? ''),
                        'reason' => implode('; ', $recordErrors),
                    ];
                    $this->writeProgressIfNeeded($processedRows, $successCount, $errorCount, $lastProgressWrite, $parseMeta);
                    continue;
                }

                try {
                    $this->upsertRecord($data);
                    $successCount++;
                } catch (\Throwable $e) {
                    $errorCount++;
                    $failedRows[] = [
                        'row_number' => $rowNumber,
                        'nim' => (string) ($data['nim'] ?? ''),
                        'nama' => (string) ($data['nama'] ?? ''),
                        'email' => (string) ($data['email'] ?? ''),
                        'reason' => $e->getMessage(),
                    ];
                    Log::error('Import alumni row failed', [
                        'row_number' => $rowNumber,
                        'nim' => $data['nim'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->writeProgressIfNeeded($processedRows, $successCount, $errorCount, $lastProgressWrite, $parseMeta);
            }

            $failedReportPath = AlumniImportReport::storeFailedRows($this->importId, $failedRows);
            $summary = $this->buildSummary(
                status: 'completed',
                processedRows: $processedRows,
                successCount: $successCount,
                errorCount: $errorCount,
                message: 'Import selesai diproses.',
                parseMeta: $parseMeta,
                failedReportPath: $failedReportPath,
            );
            AlumniImportProgress::put($this->importId, $summary);

            return $summary;
        } catch (\Throwable $e) {
            Log::error('Import Alumni Job Exception', [
                'message' => $e->getMessage(),
                'import_id' => $this->importId,
            ]);

            $summary = $this->buildSummary(
                status: 'failed',
                processedRows: $processedRows,
                successCount: $successCount,
                errorCount: $errorCount + 1,
                message: 'Import gagal diproses: ' . $e->getMessage(),
                parseMeta: $parseMeta,
            );
            AlumniImportProgress::put($this->importId, $summary);

            return $summary;
        } finally {
            @unlink($this->filePath);
        }
    }

    protected function upsertRecord(array $data): void
    {
        $prepared = $this->prepareRecord($data);

        try {
            Alumni::updateOrCreate(
                ['nim' => (string) $prepared['nim']],
                $prepared,
            );
        } catch (QueryException $e) {
            if (!$this->isDuplicateEmailError($e) || $this->mode === AlumniImportParser::MODE_STRICT) {
                throw $e;
            }

            $retryData = $prepared;
            $retryData['email'] = $this->buildFallbackEmail((string) ($prepared['nim'] ?? ''), true);

            Alumni::updateOrCreate(
                ['nim' => (string) $retryData['nim']],
                $retryData,
            );
        }
    }

    protected function prepareRecord(array $data): array
    {
        $nim = trim((string) ($data['nim'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            $email = $this->buildFallbackEmail($nim);
        }

        $owner = Alumni::query()->where('email', $email)->first();
        if ($owner && (string) $owner->nim !== $nim) {
            $email = $this->buildFallbackEmail($nim, true);
        }

        return [
            'nama' => $data['nama'] ?? null,
            'nim' => $nim,
            'nik' => $data['nik'] ?? null,
            'prodi' => $data['prodi'] ?? null,
            'fakultas' => $data['fakultas'] ?? null,
            'tahun_masuk' => $data['tahun_masuk'] ?? null,
            'tahun_lulus' => $data['tahun_lulus'] ?? null,
            'email' => $email,
            'no_hp' => $data['no_hp'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'foto' => $data['foto'] ?? null,
            'status_pekerjaan' => $data['status_pekerjaan'] ?? null,
            'sent' => $data['sent'] ?? null,
        ];
    }

    protected function isDuplicateEmailError(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate entry') && str_contains($message, 'email');
    }

    protected function buildFallbackEmail(string $nim, bool $strongUnique = false): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $nim));
        if ($base === '') {
            $base = 'alumni';
        }

        if ($strongUnique) {
            return $base . '+' . Str::lower((string) Str::uuid()) . '@import.local';
        }

        return $base . '@import.local';
    }

    protected function writeProgressIfNeeded(
        int $processedRows,
        int $successCount,
        int $errorCount,
        int &$lastProgressWrite,
        array $parseMeta,
        bool $force = false
    ): void {
        if (!$force && ($processedRows - $lastProgressWrite) < 25) {
            return;
        }

        $lastProgressWrite = $processedRows;
        AlumniImportProgress::put($this->importId, $this->buildSummary(
            status: 'processing',
            processedRows: $processedRows,
            successCount: $successCount,
            errorCount: $errorCount,
            message: 'Import sedang diproses...',
            parseMeta: $parseMeta,
        ));
    }

    /** @return array<string, mixed> */
    protected function buildSummary(
        string $status,
        int $processedRows,
        int $successCount,
        int $errorCount,
        string $message,
        array $parseMeta = [],
        ?string $failedReportPath = null
    ): array {
        $totalRows = max($this->totalRows ?? 0, $processedRows, 1);
        $percentage = (int) min(100, round(($processedRows / $totalRows) * 100));
        if ($status === 'completed') {
            $percentage = 100;
        }

        return [
            'status' => $status,
            'mode' => $this->mode,
            'message' => $message,
            'total_rows' => max($this->totalRows ?? 0, $processedRows),
            'processed_rows' => $processedRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'percentage' => $percentage,
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'format' => $parseMeta['format'] ?? 'csv',
            'delimiter' => $parseMeta['delimiter'] ?? ',',
            'encoding' => $parseMeta['encoding'] ?? 'UTF-8',
            'failed_report_path' => $failedReportPath,
        ];
    }
}

