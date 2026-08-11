<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            // batch_key: key unik per (user, type, link, window 10 menit) untuk anti-spam.
            // Format: "{user_id}:{type}:{link_url}:{10min_window}". Dipakai dispatcher
            // untuk upsert (increment batch_count) alih2 insert baris baru saat event
            // beruntun dalam 10 menit (mis. guru input 30 nilai sekaligus → 1 notif).
            $table->string('batch_key')->nullable()->after('notification_type');
            $table->unsignedInteger('batch_count')->default(1)->after('batch_key');

            // archived_at: fitur "Hapus" oleh user = soft archive (bukan delete permanen),
            // supaya audit trail tetap utuh. Query notifikasi default whereNull archived_at.
            $table->timestamp('archived_at')->nullable()->after('read_at');

            // Index untuk polling cepat: unread + belum archived + urut created_at.
            $table->index(['user_id', 'status', 'archived_at'], 'pn_user_status_archived_idx');
            $table->index(['audience_role', 'status', 'archived_at'], 'pn_role_status_archived_idx');
            $table->index('batch_key');
            $table->index('archived_at');
            // Index notification_type sudah ada di migration awal (panel_support_tables).
        });
    }

    public function down(): void
    {
        Schema::table('panel_notifications', function (Blueprint $table) {
            $table->dropIndex('pn_user_status_archived_idx');
            $table->dropIndex('pn_role_status_archived_idx');
            $table->dropIndex(['batch_key']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['batch_key', 'batch_count', 'archived_at']);
        });
    }
};