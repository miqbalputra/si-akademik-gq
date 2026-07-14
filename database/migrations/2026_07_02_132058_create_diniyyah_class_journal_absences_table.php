<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_class_journal_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_class_journal_id')->constrained('diniyyah_class_journals')->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained('class_enrollments')->cascadeOnDelete();
            $table->string('status'); // sick, permission, absent, skipped
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['diniyyah_class_journal_id', 'class_enrollment_id'], 'journal_absence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_class_journal_absences');
    }
};
