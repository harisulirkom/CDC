<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id')->index()->comment('Unique code for retrieval e.g. waiting_time, salary');
        });

        Schema::table('response_answers', function (Blueprint $table) {
            $table->bigInteger('val_int')->nullable()->index()->comment('For integers and currency');
            $table->decimal('val_decimal', 10, 2)->nullable()->index()->comment('For floats');
            $table->date('val_date')->nullable()->index()->comment('For dates');
            $table->string('val_string')->nullable()->index()->comment('Normalized lowercase string for easy searching');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('code');
        });

        Schema::table('response_answers', function (Blueprint $table) {
            $table->dropColumn(['val_int', 'val_decimal', 'val_date', 'val_string']);
        });
    }
};
