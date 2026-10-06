<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diniyyah_class_journals', function (Blueprint $table): void {
            $table->softDeletes();
            $table->string('status')->default('draft')->index();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validation_revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validation_revoked_at')->nullable();
            $table->text('validation_revocation_reason')->nullable();
        });

        Schema::create('diniyyah_class_journal_validation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diniyyah_class_journal_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('performed_at');
            $table->index(['diniyyah_class_journal_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diniyyah_class_journal_validation_logs');

        Schema::table('diniyyah_class_journals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('validation_revoked_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'validated_at', 'validation_revoked_at', 'validation_revocation_reason']);
            $table->dropSoftDeletes();
        });
    }
};
