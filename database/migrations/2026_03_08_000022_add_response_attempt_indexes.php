<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->index(['alumni_id', 'questionnaire_id', 'attempt_ke'], 'responses_alumni_qid_attempt_idx');
        });

        Schema::table('response_answers', function (Blueprint $table) {
            $table->index(['question_id', 'response_id'], 'response_answers_qid_response_idx');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex('responses_alumni_qid_attempt_idx');
        });

        Schema::table('response_answers', function (Blueprint $table) {
            $table->dropIndex('response_answers_qid_response_idx');
        });
    }
};
