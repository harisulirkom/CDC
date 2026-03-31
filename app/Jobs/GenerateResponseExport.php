<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateResponseExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $exportJobId)
    {
    }

    public function handle(): void
    {
        $export = ExportJob::find($this->exportJobId);
        if (!$export || $export->status === 'ready') {
            return;
        }

        $export->status = 'processing';
        $export->error_message = null;
        $export->save();

        try {
            $directory = storage_path('app/exports');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $path = $directory . DIRECTORY_SEPARATOR . "responses_{$export->id}.csv";
            $handle = fopen($path, 'w');

            fputcsv($handle, [
                'Nama',
                'Fakultas',
                'Prodi',
                'Tahun Masuk',
                'Tahun Lulus',
                'Status',
                'Sumber dana kuliah',
                'Masa tunggu (bulan)',
            ]);

            $filters = $export->filters ?? [];
            $query = Response::query()
                ->where('questionnaire_id', $export->questionnaire_id)
                ->with('alumni');

            $this->applyFilters($query, $filters);

            $query->orderBy('id')->chunkById(2000, function ($rows) use ($handle) {
                foreach ($rows as $response) {
                    $formData = $response->form_data ?? [];
                    $alumni = $response->alumni;

                    $row = [
                        $this->pickFirstValue($formData, ['nama', 'nama_alumni', 'name']) ?? $alumni?->nama,
                        $this->pickFirstValue($formData, ['fakultas', 'faculty']) ?? $alumni?->fakultas,
                        $this->pickFirstValue($formData, ['prodi', 'programStudi', 'program_studi', 'program']) ?? $alumni?->prodi,
                        $this->pickFirstValue($formData, ['tahunMasuk', 'tahun_masuk', 'entryYear', 'entry_year']) ?? $alumni?->tahun_masuk,
                        $this->pickFirstValue($formData, ['tahun', 'tahunLulus', 'tahun_lulus']) ?? $alumni?->tahun_lulus,
                        $this->pickFirstValue($formData, ['status', 'status_pekerjaan']) ?? ($alumni?->status_pekerjaan ?? '-'),
                        $this->pickFirstValue($formData, ['sumberDana', 'sumber_dana', 'sumber_dana_kuliah']),
                        $this->pickFirstValue($formData, [
                            'bekerja_bulanDapat',
                            'bekerja_bulanTidak',
                            'mencari_mulaiSetelah',
                            'mencari_mulaiSebelum',
                        ]),
                    ];

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);

            $export->status = 'ready';
            $export->file_path = $path;
            $export->save();
        } catch (\Throwable $e) {
            $export->status = 'failed';
            $export->error_message = $e->getMessage();
            $export->save();
            Log::error('GenerateResponseExport failed', ['error' => $e->getMessage()]);
        }
    }

    protected function applyFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('alumni', function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nim', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['fakultas'])) {
            $query->whereHas('alumni', function ($q) use ($filters) {
                $q->where('fakultas', 'like', '%' . $filters['fakultas'] . '%');
            });
        }

        if (!empty($filters['prodi'])) {
            $query->whereHas('alumni', function ($q) use ($filters) {
                $q->where('prodi', 'like', '%' . $filters['prodi'] . '%');
            });
        }

        if (!empty($filters['tahun'])) {
            $query->whereHas('alumni', function ($q) use ($filters) {
                $q->where('tahun_lulus', $filters['tahun']);
            });
        }

        if (!empty($filters['status']) && is_array($filters['status'])) {
            $statusList = array_values(array_filter(array_map('trim', $filters['status'])));
            if (!empty($statusList) && !in_array('all', $statusList, true)) {
                $query->whereHas('alumni', function ($sub) use ($statusList) {
                    $sub->whereIn('status_pekerjaan', $statusList);
                });
            }
        }

        if (!empty($filters['question_id']) && !empty($filters['answer_value'])) {
            $questionId = $filters['question_id'];
            $answerValue = strtolower(trim((string) $filters['answer_value']));
            $query->whereHas('answers', function ($q) use ($questionId, $answerValue) {
                $q->where('question_id', $questionId)
                    ->whereRaw('LOWER(jawaban) LIKE ?', ['%' . $answerValue . '%']);
            });
        }
    }

    protected function pickFirstValue(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return (string) $source[$key];
            }
        }
        return '';
    }
}
