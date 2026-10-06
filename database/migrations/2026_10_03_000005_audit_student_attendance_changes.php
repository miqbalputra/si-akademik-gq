<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->after('input_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('student_attendance_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->index(['student_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_change_logs');
        Schema::table('student_attendances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
