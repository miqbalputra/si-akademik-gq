<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'diniyyah_class_subject_id',
    'class_enrollment_id',
    'student_id',
    'meeting_number',
    'status',
    'input_by',
])]
class DiniyyahStudentAttendance extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_SICK = 'sick';
    public const STATUS_PERMISSION = 'permission';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_HOLIDAY = 'holiday';

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PRESENT => 'Hadir',
            self::STATUS_SICK => 'Sakit',
            self::STATUS_PERMISSION => 'Izin',
            self::STATUS_ABSENT => 'Alpa',
            self::STATUS_HOLIDAY => 'Libur',
        ];
    }

    /** @return array<string, string> */
    public static function codeOptions(): array
    {
        return [
            'H' => 'Hadir',
            'S' => 'Sakit',
            'I' => 'Izin',
            'A' => 'Alpa',
            'L' => 'Libur',
        ];
    }

    /** @return list<string> */
    public static function acceptedCodes(): array
    {
        return ['H', 'S', 'I', 'A', 'L', 'h', 's', 'i', 'a', 'l'];
    }

    public static function statusFromCode(?string $code): string
    {
        return match (strtoupper(trim((string) $code))) {
            'S' => self::STATUS_SICK,
            'I' => self::STATUS_PERMISSION,
            'A' => self::STATUS_ABSENT,
            'L' => self::STATUS_HOLIDAY,
            default => self::STATUS_PRESENT,
        };
    }

    public static function codeFromStatus(?string $status): string
    {
        return match ($status) {
            self::STATUS_SICK => 'S',
            self::STATUS_PERMISSION => 'I',
            self::STATUS_ABSENT => 'A',
            self::STATUS_HOLIDAY => 'L',
            default => 'H',
        };
    }

    protected function casts(): array
    {
        return [
            'meeting_number' => 'integer',
        ];
    }

    public function diniyyahClassSubject(): BelongsTo
    {
        return $this->belongsTo(DiniyyahClassSubject::class);
    }

    public function classEnrollment(): BelongsTo
    {
        return $this->belongsTo(ClassEnrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function inputBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
