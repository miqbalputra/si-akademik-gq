<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diniyyah_schedule_change_logs', function (Blueprint $table): void {
            $table->string('change_type', 24)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_reference', 120)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['entity_type', 'event', 'reviewed_at'], 'dscl_review_queue_index');
        });
    }

    public function down(): void
    {
        if (DB::table('diniyyah_schedule_change_logs')
            ->whereNotNull('change_type')
            ->orWhereNotNull('effective_from')
            ->orWhereNotNull('effective_until')
            ->orWhereNotNull('reason')
            ->orWhereNotNull('request_reference')
            ->orWhereNotNull('reviewed_at')
            ->exists()) {
            throw new RuntimeException('Tidak dapat menghapus metadata audit yang sudah dipakai. Pertahankan tabel dan kolom audit selama ada versi jadwal bertanggal.');
        }

        Schema::table('diniyyah_schedule_change_logs', function (Blueprint $table): void {
            $table->dropIndex('dscl_review_queue_index');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['change_type', 'effective_from', 'effective_until', 'reason', 'request_reference', 'reviewed_at']);
        });
    }
};
