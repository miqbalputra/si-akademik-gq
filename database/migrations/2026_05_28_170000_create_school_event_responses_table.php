<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_event_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('attendance_status')->index();
            $table->text('notes')->nullable();
            $table->timestamp('responded_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['school_event_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_event_responses');
    }
};
