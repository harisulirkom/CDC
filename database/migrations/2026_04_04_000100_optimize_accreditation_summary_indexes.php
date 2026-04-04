<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('alumnis')) {
            Schema::table('alumnis', function (Blueprint $table) {
                if (!$this->indexExists('alumnis', 'alumnis_tahun_fakultas_prodi_idx')) {
                    $table->index(['tahun_lulus', 'fakultas', 'prodi'], 'alumnis_tahun_fakultas_prodi_idx');
                }
            });
        }

        if (Schema::hasTable('responses')) {
            Schema::table('responses', function (Blueprint $table) {
                if (!$this->indexExists('responses', 'responses_qid_alumni_created_idx')) {
                    $table->index(['questionnaire_id', 'alumni_id', 'created_at'], 'responses_qid_alumni_created_idx');
                }
            });
        }

        if (Schema::hasTable('response_answers')) {
            Schema::table('response_answers', function (Blueprint $table) {
                if (!$this->indexExists('response_answers', 'response_answers_response_question_idx')) {
                    $table->index(['response_id', 'question_id'], 'response_answers_response_question_idx');
                }

                if (
                    Schema::hasColumn('response_answers', 'val_string') &&
                    !$this->indexExists('response_answers', 'response_answers_question_val_string_idx')
                ) {
                    $table->index(['question_id', 'val_string'], 'response_answers_question_val_string_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('alumnis')) {
            Schema::table('alumnis', function (Blueprint $table) {
                if ($this->indexExists('alumnis', 'alumnis_tahun_fakultas_prodi_idx')) {
                    $table->dropIndex('alumnis_tahun_fakultas_prodi_idx');
                }
            });
        }

        if (Schema::hasTable('responses')) {
            Schema::table('responses', function (Blueprint $table) {
                if ($this->indexExists('responses', 'responses_qid_alumni_created_idx')) {
                    $table->dropIndex('responses_qid_alumni_created_idx');
                }
            });
        }

        if (Schema::hasTable('response_answers')) {
            Schema::table('response_answers', function (Blueprint $table) {
                if ($this->indexExists('response_answers', 'response_answers_response_question_idx')) {
                    $table->dropIndex('response_answers_response_question_idx');
                }
                if ($this->indexExists('response_answers', 'response_answers_question_val_string_idx')) {
                    $table->dropIndex('response_answers_question_val_string_idx');
                }
            });
        }
    }

    protected function indexExists(string $tableName, string $indexName): bool
    {
        $database = DB::getDatabaseName();
        if (!$database) {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(1) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $tableName, $indexName]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
