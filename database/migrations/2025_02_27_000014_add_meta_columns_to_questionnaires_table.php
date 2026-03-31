<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->string('audience')->default('alumni')->after('status');
            $table->string('chip_text')->nullable()->after('audience');
            $table->string('estimated_time')->nullable()->after('chip_text');
            $table->boolean('is_active')->default(false)->after('estimated_time');
            $table->json('extra_questions')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropColumn(['audience', 'chip_text', 'estimated_time', 'is_active', 'extra_questions']);
        });
    }
};
