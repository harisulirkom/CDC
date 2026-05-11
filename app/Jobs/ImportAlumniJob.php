<?php

namespace App\Jobs;

use App\Models\Alumni;
use App\Services\AlumniImportProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportAlumniJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;
    protected $importId;
    protected $totalRows;

    /**
     * Create a new job instance.
     *
     * @param string $filePath Full path to the uploaded CSV file
     * @param int|null $userId ID of the admin user initiating the import
     * @param string|null $importId Tracking id for progress polling
     * @param int|null $totalRows Estimated number of rows excluding header
     */
    public function __construct(string $filePath, ?int $userId = null, ?string $importId = null, ?int $totalRows = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->importId = $importId ?: (string) Str::uuid();
        $this->totalRows = max(0, (int) ($totalRows ?? 0));
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        if (!file_exists($this->filePath)) {
            Log::error("Import failed: File not found at {$this->filePath}");
            $summary = $this->buildSummary(status: 'failed', processedRows: 0, successCount: 0, errorCount: 0, message: 'File import tidak ditemukan.');
            AlumniImportProgress::put($this->importId, $summary);
            return $summary;
        }

        Log::info("Starting Alumni Import Job for file: {$this->filePath}");

        $handle = fopen($this->filePath, 'r');
        if (!$handle) {
            Log::error("Import failed: Could not open file.");
            $summary = $this->buildSummary(status: 'failed', processedRows: 0, successCount: 0, errorCount: 0, message: 'Gagal membuka file import.');
            AlumniImportProgress::put($this->importId, $summary);
            return $summary;
        }

        $header = null;
        $batchSize = 200; // Chunk size
        $batchData = [];
        $processedRows = 0;
        $successCount = 0;
        $errorCount = 0;
        $lastProgressWrite = 0;

        AlumniImportProgress::put($this->importId, $this->buildSummary(
            status: 'processing',
            processedRows: 0,
            successCount: 0,
            errorCount: 0,
            message: 'Memulai proses import...',
        ));

        try {
            while (($row = fgetcsv($handle, 2000, ",")) !== false) {
                if (!$header) {
                    $header = $this->normalizeHeaders($row);
                    continue;
                }

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $processedRows++;

                $mapped = $this->mapRow($header, $row);
                if (!$mapped) {
                    $errorCount++;
                    $this->writeProgressIfNeeded($processedRows, $successCount, $errorCount, $lastProgressWrite);
                    continue; // Skip invalid rows
                }

                $batchData[] = $mapped;

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData, $successCount, $errorCount);
                    $batchData = []; // Clear batch
                    $this->writeProgressIfNeeded($processedRows, $successCount, $errorCount, $lastProgressWrite, true);
                }
            }

            // Process remaining
            if (!empty($batchData)) {
                $this->processBatch($batchData, $successCount, $errorCount);
                $this->writeProgressIfNeeded($processedRows, $successCount, $errorCount, $lastProgressWrite, true);
            }

            Log::info("Alumni Import Completed. Success: {$successCount}, Errors: {$errorCount}");
            $summary = $this->buildSummary(
                status: 'completed',
                processedRows: $processedRows,
                successCount: $successCount,
                errorCount: $errorCount,
                message: 'Import selesai diproses.',
            );
            AlumniImportProgress::put($this->importId, $summary);
            return $summary;

        } catch (\Exception $e) {
            Log::error("Import Job Exception: " . $e->getMessage());
            $summary = $this->buildSummary(
                status: 'failed',
                processedRows: $processedRows,
                successCount: $successCount,
                errorCount: $errorCount + 1,
                message: 'Import gagal diproses: ' . $e->getMessage(),
            );
            AlumniImportProgress::put($this->importId, $summary);
            return $summary;
        } finally {
            fclose($handle);
            @unlink($this->filePath); // Cleanup temp file
        }
    }

    protected function processBatch(array $rows, int &$successCount, int &$errorCount)
    {
        DB::beginTransaction();
        try {
            foreach ($rows as $data) {
                Alumni::updateOrCreate(
                    ['nim' => $data['nim']],
                    $data
                );
                $successCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errorCount += count($rows);
            Log::error("Batch Import Failed: " . $e->getMessage());
        }
    }

    protected function normalizeHeaders(array $rawHeaders): array
    {
        return array_map(function ($h) {
            return Str::slug(strtolower(trim($h)), '_');
        }, $rawHeaders);
    }

    protected function mapRow(array $headers, array $row): ?array
    {
        // Simple mapping, ensure key columns exist
        $data = [];
        foreach ($headers as $index => $key) {
            $data[$key] = $row[$index] ?? null;
        }

        // Map CSV headers to DB columns
        $dbData = [
            'nama' => $data['nama'] ?? $data['name'] ?? null,
            'nim' => $data['nim'] ?? null,
            'prodi' => $data['prodi'] ?? $data['program_studi'] ?? null,
            'fakultas' => $data['fakultas'] ?? $data['faculty'] ?? null,
            'tahun_lulus' => $data['tahun_lulus'] ?? $data['graduation_year'] ?? null,
            'tahun_masuk' => $data['tahun_masuk'] ?? $data['entry_year'] ?? null,
            'email' => $data['email'] ?? $data['email_address'] ?? $data['mail'] ?? null,
            'no_hp' => $data['no_hp'] ?? $data['phone'] ?? null,
        ];

        // Validate critical fields
        if (empty($dbData['nim']) || empty($dbData['nama'])) {
            return null;
        }

        // Auto-generate missing email
        if (empty($dbData['email'])) {
            $dbData['email'] = strtolower(preg_replace('/[^a-z0-9]/', '', $dbData['nim'])) . '@placeholder.local';
        }

        return $dbData;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    protected function writeProgressIfNeeded(
        int $processedRows,
        int $successCount,
        int $errorCount,
        int &$lastProgressWrite,
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
        ));
    }

    protected function buildSummary(
        string $status,
        int $processedRows,
        int $successCount,
        int $errorCount,
        string $message
    ): array {
        $totalRows = max($this->totalRows, $processedRows, 1);
        $percentage = (int) min(100, round(($processedRows / $totalRows) * 100));
        if ($status === 'completed') {
            $percentage = 100;
        }

        return [
            'status' => $status,
            'message' => $message,
            'total_rows' => max($this->totalRows, $processedRows),
            'processed_rows' => $processedRows,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'percentage' => $percentage,
            'import_id' => $this->importId,
            'user_id' => $this->userId,
        ];
    }
}
