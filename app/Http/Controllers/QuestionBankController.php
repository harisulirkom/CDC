<?php

namespace App\Http\Controllers;

use App\Http\Resources\QuestionBankResource;
use App\Models\QuestionBankItem;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index()
    {
        $items = QuestionBankItem::query()->latest()->paginate(50);

        return QuestionBankResource::collection($items);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $item = QuestionBankItem::create($data);

        return (new QuestionBankResource($item))->response()->setStatusCode(201);
    }

    public function show($id)
    {
        $item = QuestionBankItem::findOrFail($id);

        return new QuestionBankResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = QuestionBankItem::findOrFail($id);
        $data = $this->validatePayload($request);
        $item->update($data);

        // Sync changes to linked Questions in questions table
        \App\Models\Question::where('question_bank_item_id', $item->id)
            ->update([
                'pertanyaan' => $item->pertanyaan,
                'tipe' => $item->tipe,
                'pilihan' => $item->pilihan,
                'is_required' => $item->is_required,
            ]);

        // ALSO sync to extra_questions JSON in questionnaires
        $this->syncToExtraQuestions($item);

        return new QuestionBankResource($item);
    }

    public function destroy($id)
    {
        $item = QuestionBankItem::findOrFail($id);

        // Delete linked Questions in questions table
        \App\Models\Question::where('question_bank_item_id', $item->id)->delete();

        // Also remove from extra_questions JSON in questionnaires
        $this->removeFromExtraQuestions($item->id);

        $item->delete();

        return response()->noContent();
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'pertanyaan' => ['required', 'string', 'max:500'],
            'tipe' => ['required', 'string', 'max:50'],
            'pilihan' => ['nullable', 'array'],
            'is_required' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    /**
     * Sync bank item changes to extra_questions JSON in questionnaires
     */
    protected function syncToExtraQuestions(QuestionBankItem $item): void
    {
        $questionnaires = \App\Models\Questionnaire::all();

        foreach ($questionnaires as $questionnaire) {
            if (!$questionnaire->extra_questions || !is_array($questionnaire->extra_questions)) {
                continue;
            }

            $updated = false;
            $extraQuestions = $questionnaire->extra_questions;

            foreach ($extraQuestions as $key => $question) {
                // Match by questionBankItemId or question_bank_item_id
                $qBankId = $question['questionBankItemId'] ?? $question['question_bank_item_id'] ?? null;

                if ($qBankId == $item->id) {
                    // Update the question with new data from bank
                    $extraQuestions[$key]['label'] = $item->pertanyaan;
                    $extraQuestions[$key]['type'] = $item->tipe;
                    $extraQuestions[$key]['options'] = $item->pilihan ?? [];
                    $extraQuestions[$key]['required'] = $item->is_required ?? false;
                    $updated = true;
                }
            }

            if ($updated) {
                $questionnaire->extra_questions = $extraQuestions;
                $questionnaire->save();
            }
        }
    }
    /**
     * Remove question from extra_questions JSON when bank item is deleted
     */
    protected function removeFromExtraQuestions(int $itemId): void
    {
        $questionnaires = \App\Models\Questionnaire::all();

        foreach ($questionnaires as $questionnaire) {
            if (!$questionnaire->extra_questions || !is_array($questionnaire->extra_questions)) {
                continue;
            }

            $updated = false;
            $extraQuestions = $questionnaire->extra_questions;
            $newExtras = [];

            foreach ($extraQuestions as $question) {
                $qBankId = $question['questionBankItemId'] ?? $question['question_bank_item_id'] ?? null;

                if ($qBankId == $itemId) {
                    $updated = true;
                    // Skip adding this question to the newExtras array (deleting it)
                } else {
                    $newExtras[] = $question;
                }
            }

            if ($updated) {
                $questionnaire->extra_questions = $newExtras;
                $questionnaire->save();
            }
        }
    }
}
