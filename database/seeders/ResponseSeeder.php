<?php

namespace Database\Seeders;

use App\Models\Alumni;
use App\Models\Questionnaire;
use App\Models\Response;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResponseSeeder extends Seeder
{
    public function run(): void
    {
        $questionnaire = Questionnaire::where('audience', 'alumni')
            ->with('questions')
            ->latest()
            ->first();

        if (! $questionnaire || $questionnaire->questions->isEmpty()) {
            return;
        }

        $questions = $questionnaire->questions()->orderBy('urutan')->get();

        $alumniRows = Alumni::take(3)->get();

        DB::transaction(function () use ($alumniRows, $questionnaire, $questions) {
            foreach ($alumniRows as $index => $alumni) {
                $response = Response::create([
                    'alumni_id' => $alumni->id,
                    'questionnaire_id' => $questionnaire->id,
                    'attempt_ke' => $index + 1,
                ]);

                $answers = [
                    [
                        'question_id' => $questions[0]->id ?? null,
                        'jawaban' => $alumni->nama,
                    ],
                    [
                        'question_id' => $questions[1]->id ?? null,
                        'jawaban' => $alumni->nim,
                    ],
                    [
                        'question_id' => $questions[2]->id ?? null,
                        'jawaban' => $alumni->status_pekerjaan ?? 'Bekerja',
                    ],
                    [
                        'question_id' => $questions[3]->id ?? null,
                        'jawaban' => (string) (2 + $index),
                    ],
                    [
                        'question_id' => $questions[4]->id ?? null,
                        'jawaban' => (string) (6000000 + ($index * 500000)),
                    ],
                ];

                $answers = array_filter($answers, fn ($a) => ! empty($a['question_id']));

                $response->answers()->createMany($answers);
            }
        });
    }
}
