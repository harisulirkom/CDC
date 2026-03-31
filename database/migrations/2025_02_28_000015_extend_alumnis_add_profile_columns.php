<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alumnis', function (Blueprint $table) {
            $table->string('fakultas')->nullable()->after('prodi');
            $table->year('tahun_masuk')->nullable()->after('tahun_lulus');
            $table->string('nik', 50)->nullable()->after('nim');
            $table->string('no_hp', 50)->nullable()->after('nik');
            $table->text('alamat')->nullable()->after('no_hp');
            $table->date('tanggal_lahir')->nullable()->after('alamat');
            $table->string('foto')->nullable()->after('tanggal_lahir');
            $table->boolean('sent')->default(false)->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('alumnis', function (Blueprint $table) {
            $table->dropColumn([
                'fakultas',
                'tahun_masuk',
                'nik',
                'no_hp',
                'alamat',
                'tanggal_lahir',
                'foto',
                'sent',
            ]);
        });
    }
};
