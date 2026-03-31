<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->index(['questionnaire_id', 'created_at'], 'responses_qid_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropIndex('responses_qid_created_idx');
        });
    }
};
