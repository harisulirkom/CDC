<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company');
            $table->string('company_profile', 1024)->nullable();
            $table->string('location')->nullable();
            $table->string('work_mode')->nullable(); // Onsite | Hybrid | Remote
            $table->string('job_type')->nullable(); // Full-time | Contract | Internship
            $table->string('category')->nullable(); // kerja | magang | pkl
            $table->date('deadline')->nullable();
            $table->string('status')->default('draft'); // draft | published | closed
            $table->timestamp('published_at')->nullable();
            $table->text('summary')->nullable();
            $table->json('responsibilities')->nullable();
            $table->json('qualifications')->nullable();
            $table->string('compensation', 512)->nullable();
            $table->json('benefits')->nullable();
            $table->text('apply')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
