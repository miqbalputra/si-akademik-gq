<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diniyyah_class_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_teacher_assignment_id')->constrained('diniyyah_teacher_assignments')->cascadeOnDelete();
            $table->date('date');
            $table->string('session_hour'); 
            $table->text('material')->nullable();
            $table->integer('jp_count')->default(1);
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_class_journals');
    }
};
