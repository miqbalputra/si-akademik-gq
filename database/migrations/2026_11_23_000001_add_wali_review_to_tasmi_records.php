<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add metadata only: never rewrite historical results or audit records.
        Schema::table('tasmi_records', function (Blueprint $table) {
            $table->string('review_status')->nullable()->index();
            $table->unsignedInteger('review_version')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('correction_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasmi_records', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'review_version', 'reviewed_by', 'reviewed_at', 'correction_reason']);
        });
    }
};
