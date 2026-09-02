<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['diniyyah_class_subject_id', 'diniyyah_teacher_assignment_id', 'teacher_id', 'created_by', 'no_rpp', 'materi', 'alokasi_waktu', 'tujuan_pembelajaran', 'tanggal_pengesahan', 'input_method', 'ai_assisted', 'legacy_source_id', 'legacy_status', 'legacy_metadata'])]
class Rpp extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'tanggal_pengesahan' => 'date',
            'ai_assisted' => 'boolean',
            'legacy_metadata' => 'array',
        ];
    }

    public function classSubject(): BelongsTo { return $this->belongsTo(DiniyyahClassSubject::class, 'diniyyah_class_subject_id'); }
    public function teacherAssignment(): BelongsTo { return $this->belongsTo(DiniyyahTeacherAssignment::class, 'diniyyah_teacher_assignment_id'); }
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function meetings(): HasMany { return $this->hasMany(RppMeeting::class)->orderBy('urutan'); }
    public function assessment(): HasOne { return $this->hasOne(RppAssessment::class); }
    public function files(): HasMany { return $this->hasMany(RppFile::class); }
    public function exports(): HasMany { return $this->hasMany(RppExport::class); }

    public function isStructured(): bool { return $this->input_method !== 'upload'; }
}
