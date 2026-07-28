<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matrix waktu sesi diniyyah per gender (ikhwan/akhwat) + hari.
 *
 * `class_sessions` tetap sebagai identitas label sesi (Sesi 1, Sesi 2, Tafsir),
 * sedangkan jam mulai/selesai yang sebenarnya — yang berbeda antara Ikhwan &
 * Akhwat pada hari Senin, serta khusus hari Jum'at & Kamis — disimpan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_session_times', function (Blueprint $table) {
            $table->id();
            $table->string('classroom_group'); // 'ikhwan' | 'akhwat'
            $table->unsignedInteger('day_of_week'); // 1=Senin .. 7=Minggu (ISO-8601)
            $table->foreignId('class_session_id')
                ->constrained('class_sessions')
                ->cascadeOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['classroom_group', 'day_of_week', 'class_session_id'], 'cst_unique');
            $table->index(['classroom_group', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_session_times');
    }
};