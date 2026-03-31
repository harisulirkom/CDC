<?php

namespace App\Jobs;

use App\Models\Alumni;
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

    /**
     * Create a new job instance.
     *
     * @param string $filePath Full path to the uploaded CSV file
     * @param int|null $userId ID of the admin user initiating the import
     */
    public function __construct(string $filePath, ?int $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!file_exists($this->filePath)) {
            Log::error("Import failed: File not found at {$this->filePath}");
            return;
        }

        Log::info("Starting Alumni Import Job for file: {$this->filePath}");

        $handle = fopen($this->filePath, 'r');
        if (!$handle) {
            Log::error("Import failed: Could not open file.");
            return;
        }

        $header = null;
        $batchSize = 200; // Chunk size
        $batchData = [];
        $rowNumber = 0;
        $successCount = 0;
        $errorCount = 0;

        try {
            while (($row = fgetcsv($handle, 2000, ",")) !== false) {
                $rowNumber++;

                // Skip partial/empty rows
                if (count($row) < 3)
                    continue;

                if (!$header) {
                    $header = $this->normalizeHeaders($row);
                    continue;
                }

                $mapped = $this->mapRow($header, $row);
                if (!$mapped) {
                    $errorCount++;
                    continue; // Skip invalid rows
                }

                $batchData[] = $mapped;

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData, $successCount, $errorCount);
                    $batchData = []; // Clear batch
                }
            }

            // Process remaining
            if (!empty($batchData)) {
                $this->processBatch($batchData, $successCount, $errorCount);
            }

            Log::info("Alumni Import Completed. Success: {$successCount}, Errors: {$errorCount}");

        } catch (\Exception $e) {
            Log::error("Import Job Exception: " . $e->getMessage());
            // Optionally notify Admin via Notification/Email here
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
}
