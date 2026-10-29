<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'diniyyah_class_journals';

    private const OLD_INDEX = 'diniyyah_class_journals_unique_idx';

    private const ACTIVE_INDEX = 'diniyyah_class_journals_active_unique_idx';

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Indeks jurnal aktif memerlukan PostgreSQL atau SQLite. Indeks dan data lama tidak diubah.');
        }

        // Bangun pengaman pengganti lebih dulu. Tidak ada jurnal atau absensi
        // yang dihapus, dipulihkan, ataupun ditulis ulang oleh migrasi ini.
        DB::statement('CREATE UNIQUE INDEX '.self::ACTIVE_INDEX.' ON '.self::TABLE.
            ' (diniyyah_teacher_assignment_id, date, session_hour) WHERE deleted_at IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            // Database lama dapat memakai indeks mandiri hasil SQL manual,
            // sedangkan Laravel baru memakai UNIQUE constraint beserta indeksnya.
            // DROP CONSTRAINT juga melepas indeks miliknya; tangani kedua bentuk.
            DB::statement('ALTER TABLE '.self::TABLE.' DROP CONSTRAINT IF EXISTS '.self::OLD_INDEX);
            DB::statement('DROP INDEX IF EXISTS '.self::OLD_INDEX);
        } else {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::OLD_INDEX);
            });
        }
    }

    public function down(): void
    {
        // Setelah slot arsip diisi ulang, indeks lama tidak bisa dipasang tanpa
        // konflik. Hentikan rollback, jangan mengorbankan riwayat untuk memaksanya.
        $hasHistoryInSameSlot = DB::table(self::TABLE)
            ->select('diniyyah_teacher_assignment_id', 'date', 'session_hour')
            ->groupBy('diniyyah_teacher_assignment_id', 'date', 'session_hour')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasHistoryInSameSlot) {
            throw new RuntimeException('Rollback indeks jurnal dihentikan untuk menjaga riwayat: ada arsip dan jurnal lain pada slot yang sama.');
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(['diniyyah_teacher_assignment_id', 'date', 'session_hour'], self::OLD_INDEX);
        });
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::ACTIVE_INDEX);
        });
    }
};
