<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_monthly_jp_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classroom_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('homeroom_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->date('period_start');
            $table->unsignedInteger('confirmed_jp');
            $table->string('review_signature', 64);
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(
                ['classroom_term_id', 'homeroom_teacher_id', 'teacher_id', 'period_start'],
                'homeroom_monthly_jp_confirmations_unique'
            );
            $table->index(['classroom_term_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_monthly_jp_confirmations');
    }
};
