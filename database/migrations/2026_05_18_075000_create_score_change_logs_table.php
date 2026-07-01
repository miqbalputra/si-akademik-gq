<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('score_table');
            $table->unsignedBigInteger('score_id');
            $table->decimal('old_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['score_table', 'score_id']);
            $table->index('changed_by');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_change_logs');
    }
};
