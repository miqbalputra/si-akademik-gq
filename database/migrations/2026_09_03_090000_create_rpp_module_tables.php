<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diniyyah_class_subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('diniyyah_teacher_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('no_rpp', 50)->nullable();
            $table->string('materi');
            $table->string('alokasi_waktu', 100)->nullable();
            $table->text('tujuan_pembelajaran')->nullable();
            $table->date('tanggal_pengesahan')->nullable();
            $table->string('input_method', 20)->default('manual');
            $table->boolean('ai_assisted')->default(false);
            $table->string('legacy_source_id')->nullable()->unique();
            $table->string('legacy_status')->nullable();
            $table->json('legacy_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'deleted_at']);
            $table->index(['diniyyah_class_subject_id', 'deleted_at']);
            $table->index(['created_at']);
        });

        Schema::create('rpp_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->text('isi_kegiatan');
            $table->date('tanggal_kbm')->nullable();
            $table->timestamps();
            $table->unique(['rpp_id', 'urutan']);
        });

        Schema::create('rpp_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpp_id')->constrained()->cascadeOnDelete();
            $table->text('pengetahuan')->nullable();
            $table->text('keterampilan')->nullable();
            $table->text('sikap')->nullable();
            $table->timestamps();
            $table->unique('rpp_id');
        });

        Schema::create('rpp_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpp_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->default('upload');
            $table->string('disk', 40)->default('rpp');
            $table->string('path');
            $table->string('nama_file');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('ukuran_byte');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            $table->unique(['disk', 'path']);
            $table->index(['rpp_id', 'kind']);
        });

        Schema::create('rpp_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rpp_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);
            $table->string('disk', 40)->default('rpp');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->string('content_hash', 64)->nullable();
            $table->unsignedBigInteger('ukuran_byte')->nullable();
            $table->timestamps();
            $table->unique(['rpp_id', 'type']);
        });

        Schema::create('rpp_promes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diniyyah_class_subject_id')->constrained()->cascadeOnDelete();
            $table->string('url')->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->string('nama_file')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('diniyyah_class_subject_id');
        });

        Schema::create('rpp_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('endpoint')->nullable();
            $table->text('api_key')->nullable();
            $table->string('model')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rpp_import_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 50);
            $table->string('source_type', 50);
            $table->string('source_id');
            $table->string('status', 30);
            $table->foreignId('rpp_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpp_import_records');
        Schema::dropIfExists('rpp_ai_settings');
        Schema::dropIfExists('rpp_promes');
        Schema::dropIfExists('rpp_exports');
        Schema::dropIfExists('rpp_files');
        Schema::dropIfExists('rpp_assessments');
        Schema::dropIfExists('rpp_meetings');
        Schema::dropIfExists('rpps');
    }
};
