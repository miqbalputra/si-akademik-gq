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
        Schema::create('diniyyah_student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_class_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('meeting_number')->unsigned();
            $table->string('status', 20); // present, sick, permission, absent
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['diniyyah_class_subject_id', 'class_enrollment_id', 'meeting_number'], 'dsa_unique_attendance');
            $table->index('diniyyah_class_subject_id');
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diniyyah_student_attendances');
    }
};
