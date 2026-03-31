<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('format')->default('csv');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamps();
            $table->index(['questionnaire_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
