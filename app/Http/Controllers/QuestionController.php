<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Services\AuditLogger;

class QuestionController extends Controller
{
    public function index(Questionnaire $questionnaire)
    {
        $questions = $questionnaire->questions()->paginate(100);

        return QuestionResource::collection($questions);
    }

    public function store(StoreQuestionRequest $request, Questionnaire $questionnaire)
    {
        $this->authorize('create', Question::class);

        $question = $questionnaire->questions()->create($request->validated());

        AuditLogger::log('question.created', 'question', $question->id, [
            'questionnaire_id' => $questionnaire->id,
        ]);

        return new QuestionResource($question);
    }

    public function show(Question $question)
    {
        $this->authorize('view', $question);

        return new QuestionResource($question);
    }

    public function update(UpdateQuestionRequest $request, Question $question)
    {
        $this->authorize('update', $question);

        $question->update($request->validated());

        AuditLogger::log('question.updated', 'question', $question->id, [
            'questionnaire_id' => $question->questionnaire_id,
        ]);

        return new QuestionResource($question);
    }

    public function destroy(Question $question)
    {
        $this->authorize('delete', $question);

        $questionnaireId = $question->questionnaire_id;
        $question->delete();

        AuditLogger::log('question.deleted', 'question', $question->id, [
            'questionnaire_id' => $questionnaireId,
        ]);

        return response()->noContent();
    }
}
