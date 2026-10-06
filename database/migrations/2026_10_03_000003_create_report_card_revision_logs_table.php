<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_revision_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('action');
            $table->text('reason');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->index(['report_card_id', 'revision_number', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_revision_logs');
    }
};
