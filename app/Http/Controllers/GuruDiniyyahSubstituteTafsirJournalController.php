<?php

namespace App\Http\Controllers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Support\SessionTimetable;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Menu "Jurnal Pengganti Tafsir (Serentak)": pengganti menggantikan guru Tafsir
 * asli untuk beberapa kelas sekaligus pada sesi Kamis 09:50-10:20.
 *
 * Skenario: Ustadz Farhan (guru Tafsir M2-M6 Ikhwan) berhalangan; seorang
 * pengganti mengisi sesi Tafsir ke sebagian/semua kelas itu. Pengganti centang
 * kelas yang dia gantikan, isi 1 materi → terbentuk 1 jurnal pengganti per
 * kelas yang dicentang.
 *
 * Penyimpanan (sama dengan Jurnal Guru Pengganti biasa): kolom
 * `diniyyah_teacher_assignment_id` TETAP menunjuk assignment guru asli (yang
 * digantikan), dan `substitute_teacher_id` menunjuk pengganti. Dengan demikian:
 *  - mengisi slot jadwal asli (unik index assignment+date+session tetap berlaku),
 *  - muncul di daftar jurnal guru asli dengan tanda "digantikan oleh ...",
 *  - JP-nya dihitung ke pengganti (lihat DiniyyahClassJournal::effectiveTeacher()).
 *
 * Daftar kelas yang bisa digantikan = semua assignment Tafsir aktif milik guru
 * LAIN (bukan milik pengganti sendiri), dikelompokkan per nama guru asli.
 */
class GuruDiniyyahSubstituteTafsirJournalController extends Controller
{
    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $selectedDate = $request->query('date', $this->defaultThursday());

        // Assignment Tafsir aktif milik guru lain, dikelompokkan per guru asli.
        $grouped = $this->othersTafsirAssignmentsFor($teacher)
            ->groupBy(fn ($a) => $a->teacher?->name ?? '-')
            ->sortKeys();

        return view('guru.diniyyah-substitute-tafsir-journals.index', [
            'grouped' => $grouped,
            'selectedDate' => $selectedDate,
            'teacher' => $teacher,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Carbon::parse($value)->dayOfWeekIso !== 4) {
                        $fail('Tafsir hanya diadakan hari Kamis. Pilih tanggal yang jatuh di hari Kamis.');
                    }
                },
            ],
            'material' => 'required|string',
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*' => ['integer'],
        ], [
            'assignments.required' => 'Pilih minimal satu kelas yang Anda gantikan.',
            'assignments.min' => 'Pilih minimal satu kelas yang Anda gantikan.',
        ]);

        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        // Hanya assignment Tafsir aktif milik guru LAIN yang dicentang.
        // othersTafsirAssignmentsFor() sudah mengecualikan milik pengganti, jadi
        // whereIn('id', ...) menjamin pengganti tidak menggantikan dirinya sendiri
        // maupun assignment non-Tafsir.
        $others = $this->othersTafsirAssignmentsFor($teacher);
        $selected = $others->whereIn('id', $validated['assignments'])->values();
        if ($selected->isEmpty()) {
            return back()->withInput()->with('error', 'Kelas yang Anda pilih tidak valid atau bukan penugasan Tafsir guru lain.');
        }

        $created = 0;
        $skipped = 0;

        foreach ($selected as $assignment) {
            $alreadyExists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)
                ->where('date', $validated['date'])
                ->where('session_hour', SessionTimetable::SESSION_TAFSIR)
                ->exists();

            if ($alreadyExists) {
                $skipped++;
                continue;
            }

            try {
                $classroomId = $assignment->classSubject->classroomTerm->classroom_id;
                $time = SessionTimetable::resolve($classroomId, SessionTimetable::dayOfWeekIso($validated['date']), SessionTimetable::SESSION_TAFSIR)
                    ?? ['starts_at' => '09:50:00', 'ends_at' => '10:20:00'];

                DiniyyahClassJournal::create([
                    'diniyyah_teacher_assignment_id' => $assignment->id,
                    'substitute_teacher_id' => $teacher->id,
                    'date' => $validated['date'],
                    'session_hour' => SessionTimetable::SESSION_TAFSIR,
                    'session_starts_at' => $time['starts_at'],
                    'session_ends_at' => $time['ends_at'],
                    'material' => $validated['material'],
                    'jp_count' => 1,
                ]);

                $created++;
            } catch (QueryException $e) {
                // Backstop race kondisi: unique index (assignment_id, date, session_hour).
                if ($this->isDuplicateKeyException($e)) {
                    $skipped++;
                    continue;
                }

                throw $e;
            }
        }

        $message = $created.' jurnal pengganti Tafsir berhasil dibuat untuk '.$created.' kelas yang dipilih.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' kelas sudah ada jurnal Tafsir di tanggal ini (di-skip).';
        }

        return redirect()->route('guru.diniyyah-substitute-tafsir-journals.index', ['date' => $validated['date']])
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    /**
     * Semua assignment Tafsir aktif milik guru LAIN (bukan pengganti yang login),
     * eager-load classSubject.subject + classroomTerm.classroom + teacher.
     */
    private function othersTafsirAssignmentsFor($teacher)
    {
        return DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom', 'teacher'])
            ->where('teacher_id', '!=', $teacher->id)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->get()
            ->filter(fn ($a) => $a->classSubject?->subject
                && (strtolower($a->classSubject->subject->code) === SessionTimetable::SESSION_TAFSIR
                    || str_contains(strtolower($a->classSubject->subject->name), 'tafsir')))
            ->values();
    }

    private function defaultThursday(): string
    {
        // WIB — app tz=UTC, agar "hari Kamis?" & "Kamis depan" tidak meleset di larut malam WIB.
        $today = Carbon::now('Asia/Jakarta');

        return $today->isThursday() ? $today->toDateString() : $today->next(Carbon::THURSDAY)->toDateString();
    }

    /**
     * Deteksi QueryException pelanggaran unique constraint lintas driver
     * (SQLite error code 19 / MySQL SQLSTATE 23000).
     */
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlstate = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlstate === '23000' || $driverCode === 19;
    }
}