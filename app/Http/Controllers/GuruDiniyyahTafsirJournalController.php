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
 * Menu "Jurnal Tafsir": input serentak 1 materi → N jurnal (satu per kelas
 * Tafsir) untuk guru yang mengajar Tafsir ke beberapa kelas sekaligus.
 *
 * Skenario: Kamis 09:50-10:20, 1 Ustadz mengajar Tafsir ke M2-M6 Ikhwan,
 * 1 Ustadzah ke M2-M6 Akhwat. Alih-alih mengisi 5 jurnal manual, guru cukup
 * input 1 materi di sini → terbentuk 5 jurnal (session_hour='tafsir',
 * snapshot 09:50-10:20), satu per penugasan Tafsir miliknya.
 *
 * Identifikasi penugasan Tafsir: assignment yang subject-nya ber-code 'tafsir'
 * (atau nama mengandung 'Tafsir'). Subject Tafsir ditambahkan via migration
 * 000005; DiniyyahClassSubject + assignment di-set admin via Filament.
 *
 * Forward-compatible: bila guru belum punya penugasan Tafsir, halaman menampilkan
 * pesan (form disembunyikan) — tanpa error.
 */
class GuruDiniyyahTafsirJournalController extends Controller
{
    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $tafsirAssignments = $this->tafsirAssignmentsFor($teacher);
        $selectedDate = $request->query('date', $this->defaultThursday());

        return view('guru.diniyyah-tafsir-journals.index', [
            'tafsirAssignments' => $tafsirAssignments,
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
            'assignments.required' => 'Pilih minimal satu kelas yang ikut sesi Tafsir.',
            'assignments.min' => 'Pilih minimal satu kelas yang ikut sesi Tafsir.',
        ]);

        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $tafsirAssignments = $this->tafsirAssignmentsFor($teacher);
        if ($tafsirAssignments->isEmpty()) {
            return redirect()->route('guru.diniyyah-tafsir-journals.index')
                ->with('error', 'Anda belum memiliki penugasan Tafsir. Minta admin menambahkannya di menu Diniyyah (subject Tafsir Al Quran + penugasan ke kelas Anda).');
        }

        // Hanya assignment Tafsir milik guru sendiri yang dicentang. Karena
        // tafsirAssignmentsFor() sudah memfilter teacher_id, whereIn('id', ...)
        // menjamin tidak ada assignment guru lain yang lolos walau di-injeksi.
        $selected = $tafsirAssignments->whereIn('id', $validated['assignments'])->values();
        if ($selected->isEmpty()) {
            return back()->withInput()->with('error', 'Kelas yang Anda pilih tidak valid atau bukan penugasan Tafsir Anda.');
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

        $message = $created.' jurnal Tafsir berhasil dibuat untuk '.$created.' kelas yang dipilih.';
        if ($skipped > 0) {
            $message .= ' '.$skipped.' kelas sudah ada jurnal Tafsir di tanggal ini (di-skip).';
        }

        return redirect()->route('guru.diniyyah-tafsir-journals.index', ['date' => $validated['date']])
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    /**
     * Penugasan Tafsir milik guru (subject code 'tafsir' atau nama mengandung
     * 'Tafsir'), eager-load classSubject.subject + classroomTerm.classroom.
     */
    private function tafsirAssignmentsFor($teacher)
    {
        return DiniyyahTeacherAssignment::with(['classSubject.subject', 'classSubject.classroomTerm.classroom'])
            ->where('teacher_id', $teacher->id)
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