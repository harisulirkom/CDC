<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cta_slides', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->nullable();
            $table->string('title');
            $table->string('highlight')->nullable();
            $table->string('subtitle', 1024)->nullable();
            $table->json('chips')->nullable();
            $table->json('primary')->nullable();
            $table->json('secondary')->nullable();
            $table->json('stats')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cta_slides');
    }
};
