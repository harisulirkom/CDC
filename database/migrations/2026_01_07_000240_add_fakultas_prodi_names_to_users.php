<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fakultas')) {
                $table->string('fakultas')->nullable()->after('prodi_id');
            }
            if (!Schema::hasColumn('users', 'prodi')) {
                $table->string('prodi')->nullable()->after('fakultas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fakultas')) {
                $table->dropColumn('fakultas');
            }
            if (Schema::hasColumn('users', 'prodi')) {
                $table->dropColumn('prodi');
            }
        });
    }
};
