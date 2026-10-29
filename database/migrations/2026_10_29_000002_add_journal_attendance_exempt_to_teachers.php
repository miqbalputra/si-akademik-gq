<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only: journals, student attendance and archived records are untouched.
        Schema::table('teachers', function (Blueprint $table): void {
            $table->boolean('journal_attendance_exempt')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropColumn('journal_attendance_exempt');
        });
    }
};
