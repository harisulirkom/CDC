<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('alumnis', 'email')) {
            Schema::table('alumnis', function (Blueprint $table) {
                $table->string('email')->nullable()->unique()->after('nim');
            });
        }
    }

    public function down(): void
    {
        // no-op to avoid dropping existing email column
    }
};
