<?php

namespace App\Http\Controllers;

use App\Models\DiniyyahClassJournal;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\DiniyyahTeachingSchedule;
use App\Services\DiniyyahNoKbmAgendaService;
use App\Services\TafsirScheduleGroupingService;
use App\Support\SessionTimetable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Menu "Jurnal Tafsir": input serentak 1 materi → N jurnal (satu per kelas
 * Tafsir) untuk guru yang mengajar Tafsir ke beberapa kelas sekaligus.
 *
 * Skenario: satu guru mengajar Tafsir ke beberapa kelas pada hari dan jam yang
 * sama. Alih-alih mengisi beberapa jurnal manual, guru cukup input 1 materi
 * di sini → terbentuk 1 jurnal per kelas dalam kelompok serentak tersebut.
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
    public function __construct(
        private readonly DiniyyahNoKbmAgendaService $noKbmAgendaService,
        private readonly TafsirScheduleGroupingService $tafsirScheduleGroupingService,
    ) {}

    public function index(Request $request)
    {
        $teacher = Auth::user()->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $tafsirAssignments = $this->tafsirAssignmentsFor($teacher);
        $selectedDate = $request->query('date', $this->defaultTafsirDate());
        $schedules = $this->tafsirSchedulesFor($teacher);
        $requestedAssignmentIds = collect($request->query('assignment_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $agendaEvents = $this->noKbmAgendaService->eventsForRange(
            $tafsirAssignments->pluck('classSubject.classroomTerm')->filter()->unique('id')->values(),
            Carbon::parse($selectedDate, 'Asia/Jakarta')->startOfDay(),
            Carbon::parse($selectedDate, 'Asia/Jakarta')->endOfDay(),
        );
        $simultaneousGroups = $this->tafsirScheduleGroupingService
            ->simultaneousGroupsForDate($schedules, $selectedDate)
            ->map(function (array $group) use ($agendaEvents, $selectedDate, $requestedAssignmentIds): array {
                $assignments = $group['schedules']
                    ->map(fn ($schedule) => $schedule->teacherAssignment)
                    ->filter()
                    ->unique('id')
                    ->values();
                $agendaAssignments = $assignments
                    ->filter(fn ($assignment) => $this->noKbmAgendaService->forClassroomTerm(
                        $agendaEvents,
                        $assignment->classSubject->classroomTerm,
                        $selectedDate,
                    ) !== null)
                    ->mapWithKeys(fn ($assignment) => [
                        $assignment->id => $this->noKbmAgendaService->forClassroomTerm(
                            $agendaEvents,
                            $assignment->classSubject->classroomTerm,
                            $selectedDate,
                        ),
                    ]);

                $group['assignments'] = $assignments;
                $group['agenda_assignments'] = $agendaAssignments;
                $group['preselected_assignment_ids'] = $assignments
                    ->whereIn('id', $requestedAssignmentIds)
                    ->pluck('id')
                    ->all();

                return $group;
            });

        return view('guru.diniyyah-tafsir-journals.index', [
            'tafsirAssignments' => $tafsirAssignments,
            'selectedDate' => $selectedDate,
            'teacher' => $teacher,
            'simultaneousGroups' => $simultaneousGroups,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
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

        $schedules = $this->tafsirSchedulesFor($teacher);
        if ($schedules->isEmpty()) {
            return redirect()->route('guru.diniyyah-tafsir-journals.index')
                ->with('error', 'Anda belum memiliki penugasan Tafsir. Minta admin menambahkannya di menu Diniyyah (subject Tafsir Al Quran + penugasan ke kelas Anda).');
        }

        $group = $this->tafsirScheduleGroupingService->groupContainingAssignments(
            $this->tafsirScheduleGroupingService->simultaneousGroupsForDate($schedules, $validated['date']),
            $validated['assignments'],
        );
        if ($group === null) {
            return back()->withInput()->with('error', 'Kelas yang dipilih bukan bagian dari satu sesi Tafsir serentak yang dijadwalkan pada tanggal tersebut. Tafsir individual diisi melalui Jurnal Kelas.');
        }

        $selectedSchedules = $group['schedules']
            ->whereIn('diniyyah_teacher_assignment_id', $validated['assignments'])
            ->values();
        $selected = $selectedSchedules
            ->map(fn ($schedule) => $schedule->teacherAssignment)
            ->filter()
            ->unique('id')
            ->values();

        $agendaEvents = $this->noKbmAgendaService->eventsForRange(
            $selected->pluck('classSubject.classroomTerm')->filter()->unique('id')->values(),
            Carbon::parse($validated['date'], 'Asia/Jakarta')->startOfDay(),
            Carbon::parse($validated['date'], 'Asia/Jakarta')->endOfDay(),
        );
        $agendaAssignments = $selected->filter(fn ($assignment) =>
            $this->noKbmAgendaService->forClassroomTerm($agendaEvents, $assignment->classSubject->classroomTerm, $validated['date']) !== null
        );
        $selected = $selected->reject(fn ($assignment) => $agendaAssignments->contains('id', $assignment->id))->values();

        $created = 0;
        $skipped = 0;
        $agendaSkipped = $agendaAssignments->count();

        foreach ($selectedSchedules as $schedule) {
            $assignment = $schedule->teacherAssignment;
            if ($agendaAssignments->contains('id', $assignment->id)) {
                continue;
            }

            $alreadyExists = DiniyyahClassJournal::where('diniyyah_teacher_assignment_id', $assignment->id)
                ->where('date', $validated['date'])
                ->get()
                ->contains(fn ($journal) => $this->tafsirScheduleGroupingService->journalMatchesSchedule($journal, $schedule));

            if ($alreadyExists) {
                $skipped++;

                continue;
            }

            try {
                DiniyyahClassJournal::create([
                    'diniyyah_teacher_assignment_id' => $assignment->id,
                    'date' => $validated['date'],
                    'session_hour' => SessionTimetable::SESSION_TAFSIR,
                    'session_starts_at' => $group['starts_at'],
                    'session_ends_at' => $group['ends_at'],
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
        if ($agendaSkipped > 0) {
            $message .= ' '.$agendaSkipped.' kelas dibebaskan oleh agenda tanpa KBM.';
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

    private function tafsirSchedulesFor($teacher)
    {
        return DiniyyahTeachingSchedule::with([
            'teacherAssignment.classSubject.subject',
            'teacherAssignment.classSubject.classroomTerm.classroom',
            'classSession',
        ])->whereHas('teacherAssignment', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->get()
            ->filter(fn ($schedule) => $this->tafsirScheduleGroupingService->isTafsirSchedule($schedule))
            ->values();
    }

    private function defaultTafsirDate(): string
    {
        // Default tanggal hari ini dalam WIB. Jika guru membuka menu sebelum
        // jadwal serentaknya, ia dapat memilih tanggal jadwal melalui filter.
        return Carbon::now('Asia/Jakarta')->toDateString();
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
