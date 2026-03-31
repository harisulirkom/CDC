<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('question_bank_item_id')
                ->nullable()
                ->constrained('question_bank_items')
                ->nullOnDelete();
            // If bank item is deleted, we might want to keep the question but unlink it, 
            // OR delete it. User said "perubahan baik dihapus".
            // Let's stick with nullOnDelete for safety in the constraint, 
            // but we will implement explicit logic in the Controller to handle the sync.
            // Actually, to fully comply with "dihapus... perubahan terjadi dihalaman kuisioner", 
            // deleting the question in the questionnaire seems expected.
            // But foreign key cascade delete might be too aggressive if we want logic.
            // Let's use nullOnDelete for the DB constraint so we don't break things accidentally,
            // and handle the logic in the Controller.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['question_bank_item_id']);
            $table->dropColumn('question_bank_item_id');
        });
    }
};
