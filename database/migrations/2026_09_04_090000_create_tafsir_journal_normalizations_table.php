<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tafsir_journal_normalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diniyyah_class_journal_id')->constrained()->cascadeOnDelete();
            $table->string('group_key', 160);
            $table->string('original_session_hour', 40);
            $table->foreignId('normalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('normalized_at');
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['group_key', 'normalized_at']);
            $table->index(['diniyyah_class_journal_id', 'reverted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tafsir_journal_normalizations');
    }
};
