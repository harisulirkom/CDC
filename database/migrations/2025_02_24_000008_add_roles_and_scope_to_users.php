<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('password')->nullable()->constrained('roles');
            $table->unsignedBigInteger('fakultas_id')->nullable()->after('role_id');
            $table->unsignedBigInteger('prodi_id')->nullable()->after('fakultas_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['fakultas_id', 'prodi_id']);
        });

        Schema::dropIfExists('roles');
    }
};
