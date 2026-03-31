<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->string('pertanyaan');
            $table->string('tipe')->default('text');
            $table->json('pilihan')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_items');
    }
};
