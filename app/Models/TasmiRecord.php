<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TasmiRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_term_id',
        'classroom_term_id',
        'class_enrollment_id',
        'student_id',
        'examiner_teacher_id',
        'tasmi_examiner_assignment_id',
        'exam_type',
        'juz_start',
        'juz_end',
        'exam_day_label',
        'exam_date',
        'hijri_date',
        'predicate',
        'notes',
        'input_by',
        'input_at',
        'last_updated_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'input_at' => 'datetime',
        'juz_start' => 'integer',
        'juz_end' => 'integer',
    ];

    public const EXAM_TYPE_ONE_JUZ = '1_juz';
    public const EXAM_TYPE_FIVE_JUZ = '5_juz';

    public const PREDICATE_MAQBUL = 'maqbul';
    public const PREDICATE_JAYYID = 'jayyid';
    public const PREDICATE_JAYYID_JIDDAN = 'jayyid_jiddan';
    public const PREDICATE_MUMTAZ = 'mumtaz';

    public static function examTypeOptions(): array
    {
        return [
            self::EXAM_TYPE_ONE_JUZ => 'Tasmi\' 1 Juz',
            self::EXAM_TYPE_FIVE_JUZ => 'Tasmi\' 5 Juz',
        ];
    }

    public static function predicateOptions(): array
    {
        return [
            self::PREDICATE_MAQBUL => 'Maqbul',
            self::PREDICATE_JAYYID => 'Jayyid',
            self::PREDICATE_JAYYID_JIDDAN => 'Jayyid Jiddan',
            self::PREDICATE_MUMTAZ => 'Mumtaz',
        ];
    }

    public static function predicateLabel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::predicateOptions()[$value] ?? $value;
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function classroomTerm(): BelongsTo
    {
        return $this->belongsTo(ClassroomTerm::class);
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function examinerTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'examiner_teacher_id');
    }

    public function examinerAssignment(): BelongsTo
    {
        return $this->belongsTo(TasmiExaminerAssignment::class, 'tasmi_examiner_assignment_id');
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function getJuzRangeLabelAttribute(): string
    {
        if ($this->juz_start === $this->juz_end) {
            return "Juz {$this->juz_start}";
        }

        return "Juz {$this->juz_start} - {$this->juz_end}";
    }
}