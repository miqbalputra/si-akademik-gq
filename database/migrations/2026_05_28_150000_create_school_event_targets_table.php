<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_event_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_event_id', 'classroom_term_id']);
            $table->index('classroom_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_event_targets');
    }
};
