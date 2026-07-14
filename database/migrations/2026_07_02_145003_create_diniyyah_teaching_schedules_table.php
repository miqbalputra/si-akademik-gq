<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('diniyyah_teaching_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_teacher_assignment_id')->constrained('diniyyah_teacher_assignments')->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->integer('day_of_week'); // 1 = Monday, 7 = Sunday
            $table->timestamps();

            // A teacher assignment shouldn't have duplicate schedules for the exact same day and session
            $table->unique(['diniyyah_teacher_assignment_id', 'day_of_week', 'class_session_id'], 'dts_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diniyyah_teaching_schedules');
    }
};
