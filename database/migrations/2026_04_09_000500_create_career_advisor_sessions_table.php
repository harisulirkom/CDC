<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_advisor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 64)->unique();
            $table->string('persona_id', 32);
            $table->json('profile_data')->nullable();
            $table->unsignedTinyInteger('form_completion_percent')->default(0);
            $table->string('confidence_band', 16)->default('rendah');
            $table->boolean('ready_for_generate')->default(false);
            $table->string('generation_status', 24)->default('idle');
            $table->string('analysis_id', 64)->nullable()->index();
            $table->json('recommendation_data')->nullable();
            $table->string('recommendation_source', 32)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('next_action', 32)->nullable();
            $table->timestamp('action_saved_at')->nullable();
            $table->unsignedTinyInteger('relevance_score')->nullable();
            $table->text('feedback_note')->nullable();
            $table->timestamp('feedback_saved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'generation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_advisor_sessions');
    }
};
