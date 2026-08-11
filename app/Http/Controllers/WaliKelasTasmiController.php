<?php

namespace App\Http\Controllers;

use App\Models\ClassroomTerm;
use App\Models\HomeroomAssignment;
use App\Models\TasmiRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WaliKelasTasmiController extends Controller
{
    /**
     * Tampilkan data tasmi' untuk santri di kelas yang diwalikan guru ini.
     * Read-only — wali kelas hanya melihat, tidak bisa input/edit.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');

        // Classroom terms yang diwalikan guru ini (aktif atau yang masih dalam rentang tanggal).
        $homeroomClassroomTerms = ClassroomTerm::query()
            ->with(['classroom', 'academicTerm.academicYear'])
            ->whereHas('homeroomAssignments', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->where(function ($q) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
                    })
                    ->where(function ($q) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
                    });
            })
            ->get();

        $classroomTermIds = $homeroomClassroomTerms->pluck('id');

        $query = TasmiRecord::query()
            ->with(['student', 'classroomTerm.classroom', 'examinerTeacher', 'academicTerm'])
            ->whereIn('classroom_term_id', $classroomTermIds);

        if ($search = $request->query('search')) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%");
            });
        }
        if ($examType = $request->query('exam_type')) {
            $query->where('exam_type', $examType);
        }
        if ($predicate = $request->query('predicate')) {
            $query->where('predicate', $predicate);
        }
        if ($dateFrom = $request->query('date_from')) {
            $query->where('exam_date', '>=', $dateFrom);
        }
        if ($dateUntil = $request->query('date_until')) {
            $query->where('exam_date', '<=', $dateUntil);
        }
        if ($classroomTermId = $request->query('classroom_term_id')) {
            $query->where('classroom_term_id', $classroomTermId);
        }

        $records = $query->latest('exam_date')->latest('id')->paginate(20)->withQueryString();

        return view('guru.tasmi.wali-view', [
            'teacher' => $teacher,
            'records' => $records,
            'homeroomClassroomTerms' => $homeroomClassroomTerms,
            'examTypeOptions' => TasmiRecord::examTypeOptions(),
            'predicateOptions' => TasmiRecord::predicateOptions(),
            'filters' => $request->only(['search', 'exam_type', 'predicate', 'date_from', 'date_until', 'classroom_term_id']),
        ]);
    }

    public function show(Request $request, TasmiRecord $tasmi_record): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);

        // Wali kelas hanya boleh lihat record santri yang ada di kelas yang diwalinya.
        $isHomeroomOfThisClass = HomeroomAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('classroom_term_id', $tasmi_record->classroom_term_id)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $tasmi_record->exam_date?->toDateString());
            })
            ->where(function ($q) use ($tasmi_record) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $tasmi_record->exam_date?->toDateString());
            })
            ->exists();

        $isAdmin = $user->hasRole('admin');
        abort_unless($isHomeroomOfThisClass || $isAdmin, 403, 'Anda hanya bisa melihat data tasmi\' santri dari kelas yang Anda wali.');

        $tasmi_record->load(['student', 'classroomTerm.classroom', 'examinerTeacher', 'academicTerm.academicYear', 'inputBy']);

        return view('guru.tasmi.show', [
            'record' => $tasmi_record,
        ]);
    }
}