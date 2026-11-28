<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahfidz_report_entries', function (Blueprint $table) {
            $table->string('input_role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tahfidz_report_entries', function (Blueprint $table) {
            $table->dropColumn('input_role');
        });
    }
};
