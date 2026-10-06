<?php

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Student;
use App\Models\StudentDeparture;
use App\Models\TahfidzHalaqahMember;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentDepartureWorkflow
{
    /** @param array<string, mixed> $data */
    public function depart(Student $student, array $data, User $actor): StudentDeparture
    {
        $this->authorizeAdmin($actor);

        $validated = Validator::make($data, [
            'type' => ['required', Rule::in([StudentDeparture::TYPE_TRANSFER, StudentDeparture::TYPE_LEFT])],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:5000'],
            'destination_school' => [
                Rule::requiredIf(($data['type'] ?? null) === StudentDeparture::TYPE_TRANSFER),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'destination_school.required' => 'Sekolah tujuan wajib diisi untuk siswa pindah.',
        ])->validate();

        return DB::transaction(function () use ($student, $validated, $actor): StudentDeparture {
            $student = Student::withTrashed()->lockForUpdate()->findOrFail($student->getKey());

            if ($student->trashed()) {
                throw new DomainException('Siswa ini sudah berada di arsip.');
            }

            $departure = $student->departures()->create([
                'type' => $validated['type'],
                'effective_date' => $validated['effective_date'],
                'reason' => trim($validated['reason']),
                'destination_school' => $validated['type'] === StudentDeparture::TYPE_TRANSFER
                    ? trim($validated['destination_school'])
                    : null,
                'exited_by' => $actor->getKey(),
            ]);

            ClassEnrollment::query()
                ->where('student_id', $student->getKey())
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            TahfidzHalaqahMember::query()
                ->where('student_id', $student->getKey())
                ->where('status', 'active')
                ->update([
                    'status' => 'inactive',
                    'left_at' => $validated['effective_date'],
                ]);

            $student->status = 'inactive';
            $student->save();
            $student->delete();

            return $departure;
        });
    }

    public function restoreProfile(StudentDeparture $departure, User $actor): Student
    {
        $this->authorizeAdmin($actor);

        return DB::transaction(function () use ($departure, $actor): Student {
            $departure = StudentDeparture::query()->lockForUpdate()->findOrFail($departure->getKey());
            $student = Student::withTrashed()->lockForUpdate()->findOrFail($departure->student_id);

            if (! $student->trashed() || $departure->restored_at !== null) {
                throw new DomainException('Profil siswa ini sudah dipulihkan atau tidak lagi berada di arsip.');
            }

            $student->restore();
            $student->status = 'active';
            $student->save();

            $departure->update([
                'restored_at' => now(),
                'restored_by' => $actor->getKey(),
            ]);

            return $student;
        });
    }

    private function authorizeAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            abort(403);
        }
    }
}
