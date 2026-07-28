<?php

use App\Models\DiniyyahSubject;
use Illuminate\Database\Migrations\Migration;

/**
 * Tambahkan subject Diniyyah "Tafsir Al Quran" (code 'tafsir').
 *
 * Tafsir diajar serentak Kamis 09:50-10:20 oleh 1 Ustadz (M2-M6 Ikhwan) dan
 * 1 Ustadzah (M2-M6 Akhwat). Sebelumnya subject Tafsir belum di-seed, sehingga
 * tidak ada DiniyyahClassSubject/assignment Tafsir — fitur "Jurnal Tafsir
 * serentak" butuh assignment Tafsir yang menunjuk subject ini.
 *
 * Migration ini hanya menambahkan subject-nya. DiniyyahClassSubject Tafsir per
 * kelas + assignment guru Tafsir di-set admin via Filament setelah deploy.
 *
 * Berjalan otomatis saat migrate (deploy Coolify). Idempoten (firstOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        DiniyyahSubject::firstOrCreate(
            ['code' => 'tafsir'],
            [
                'name' => 'Tafsir Al Quran',
                'default_assessment_method' => 'weighted',
                'sort_order' => 70,
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        DiniyyahSubject::where('code', 'tafsir')->delete();
    }
};