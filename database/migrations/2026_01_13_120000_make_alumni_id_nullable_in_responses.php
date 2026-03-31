<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Require doctrine/dbal for change(), if not present, this might fail unless using latest Laravel/MySQL
        // We will try using raw statement if standard way fails, but let's try standard way first.
        // Actually, for safety, let's use raw SQL for MySQL which is common.
        // But let's try Schema first.
        Schema::table('responses', function (Blueprint $table) {
            $table->unsignedBigInteger('alumni_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->unsignedBigInteger('alumni_id')->nullable(false)->change();
        });
    }
};
