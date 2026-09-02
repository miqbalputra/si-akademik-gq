<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateRppExport;
use App\Models\Rpp;
use App\Models\RppExport;
use App\Models\RppFile;
use App\Models\RppPromes;
use App\Services\NotificationDispatcher;
use App\Services\RppAccessService;
use App\Services\RppAiService;
use App\Services\RppExportService;
use App\Services\RppStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class GuruRppController extends Controller
{
    public function __construct(private readonly RppAccessService $access) {}

    public function index(Request $request): View
    {
        $teacher = $this->teacher($request);
        $rpps = Rpp::query()
            ->with(['classSubject.subject', 'classSubject.classroomTerm', 'meetings'])
            ->where('teacher_id', $teacher->id)
            ->when($request->filled('q'), fn ($query) => $query->where(function ($q) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where('materi', 'like', $term)->orWhere('no_rpp', 'like', $term)
                    ->orWhereHas('classSubject.subject', fn ($subject) => $subject->where('name', 'like', $term));
            }))
            ->latest()->paginate(15)->withQueryString();

        return view('guru.rpp.index', compact('rpps'));
    }

    public function create(Request $request): View
    {
        $this->teacher($request);
        $assignments = $this->access->activeAssignments($request->user());
        $draft = $request->session()->pull('rpp.ai_draft');

        return view('guru.rpp.form', ['rpp' => null, 'assignments' => $assignments, 'draft' => $draft]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->teacher($request);
        $method = $request->input('input_method', 'manual');
        abort_unless(in_array($method, ['manual', 'ai', 'upload'], true), 422);

        $rules = [
            'diniyyah_class_subject_id' => ['required', 'integer'],
            'no_rpp' => ['nullable', 'string', 'max:50'],
            'tanggal_pengesahan' => ['nullable', 'date'],
        ];
        if ($method === 'upload') {
            $rules['file'] = ['required', 'file', 'mimetypes:application/pdf', 'max:10240'];
        } else {
            $rules += [
                'materi' => ['required', 'string', 'max:255'],
                'alokasi_waktu' => ['required', 'string', 'max:100'],
                'tujuan_pembelajaran' => ['required', 'string'],
                'meetings' => ['required', 'array', 'min:1', 'max:12'],
                'meetings.*.isi_kegiatan' => ['required', 'string'],
                'meetings.*.tanggal_kbm' => ['nullable', 'date'],
                'pengetahuan' => ['required', 'string'],
                'keterampilan' => ['required', 'string'],
                'sikap' => ['required', 'string'],
            ];
        }
        $data = $request->validate($rules);
        $assignment = $this->access->assignmentFor($request->user(), (int) $data['diniyyah_class_subject_id']);

        $rpp = DB::transaction(function () use ($request, $data, $assignment, $method): Rpp {
            $rpp = Rpp::create([
                'diniyyah_class_subject_id' => $assignment->diniyyah_class_subject_id,
                'diniyyah_teacher_assignment_id' => $assignment->id,
                'teacher_id' => $assignment->teacher_id,
                'created_by' => $request->user()->id,
                'no_rpp' => $data['no_rpp'] ?? null,
                'materi' => $method === 'upload' ? pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME) : $data['materi'],
                'alokasi_waktu' => $method === 'upload' ? null : $data['alokasi_waktu'],
                'tujuan_pembelajaran' => $method === 'upload' ? null : $data['tujuan_pembelajaran'],
                'tanggal_pengesahan' => $data['tanggal_pengesahan'] ?? now()->toDateString(),
                'input_method' => $method,
                'ai_assisted' => $method === 'ai',
            ]);

            if ($method !== 'upload') {
                foreach ($data['meetings'] as $index => $meeting) {
                    $rpp->meetings()->create([
                        'urutan' => $index + 1,
                        'isi_kegiatan' => $meeting['isi_kegiatan'],
                        'tanggal_kbm' => $meeting['tanggal_kbm'] ?? null,
                    ]);
                }
                $rpp->assessment()->create([
                    'pengetahuan' => $data['pengetahuan'],
                    'keterampilan' => $data['keterampilan'],
                    'sikap' => $data['sikap'],
                ]);
            }

            return $rpp;
        });

        if ($method === 'upload') {
            app(RppStorageService::class)->storeUpload($rpp, $request->file('file'));
        } else {
            foreach (['pdf', 'png', 'docx'] as $type) {
                GenerateRppExport::dispatch($rpp->id, $type);
            }
        }

        app(NotificationDispatcher::class)->dispatchToRole(
            'kabag_diniyyah', 'RPP baru', "{$rpp->teacher->name} membuat RPP: {$rpp->materi}.", 'rpp_created', route('guru.rpp.show', $rpp), 'info',
        );

        return redirect()->route('guru.rpp.show', $rpp)->with('success', 'RPP berhasil disimpan.');
    }

    public function show(Request $request, Rpp $rpp): View
    {
        abort_unless($request->user()->can('view', $rpp), 403);
        $rpp->load(['classSubject.subject', 'classSubject.classroomTerm.academicTerm.academicYear', 'teacher', 'meetings', 'assessment', 'files', 'exports']);
        $canManage = $this->access->canManage($request->user(), $rpp);

        return view('guru.rpp.show', compact('rpp', 'canManage'));
    }

    public function edit(Request $request, Rpp $rpp): View
    {
        abort_unless($request->user()->can('update', $rpp), 403);
        abort_unless($rpp->isStructured(), 422, 'RPP PDF unggahan tidak memiliki form terstruktur untuk diedit.');
        $rpp->load(['meetings', 'assessment']);

        return view('guru.rpp.form', ['rpp' => $rpp, 'assignments' => $this->access->activeAssignments($request->user()), 'draft' => null]);
    }

    public function update(Request $request, Rpp $rpp): RedirectResponse
    {
        abort_unless($request->user()->can('update', $rpp), 403);
        abort_unless($rpp->isStructured(), 422);
        $data = $request->validate([
            'diniyyah_class_subject_id' => ['required', 'integer'], 'no_rpp' => ['nullable', 'string', 'max:50'],
            'materi' => ['required', 'string', 'max:255'], 'alokasi_waktu' => ['required', 'string', 'max:100'],
            'tujuan_pembelajaran' => ['required', 'string'], 'tanggal_pengesahan' => ['nullable', 'date'],
            'meetings' => ['required', 'array', 'min:1', 'max:12'], 'meetings.*.isi_kegiatan' => ['required', 'string'], 'meetings.*.tanggal_kbm' => ['nullable', 'date'],
            'pengetahuan' => ['required', 'string'], 'keterampilan' => ['required', 'string'], 'sikap' => ['required', 'string'],
        ]);
        $assignment = $this->access->assignmentFor($request->user(), (int) $data['diniyyah_class_subject_id']);

        DB::transaction(function () use ($rpp, $data, $assignment): void {
            $rpp->update([
                'diniyyah_class_subject_id' => $assignment->diniyyah_class_subject_id, 'diniyyah_teacher_assignment_id' => $assignment->id,
                'no_rpp' => $data['no_rpp'] ?? null, 'materi' => $data['materi'], 'alokasi_waktu' => $data['alokasi_waktu'],
                'tujuan_pembelajaran' => $data['tujuan_pembelajaran'], 'tanggal_pengesahan' => $data['tanggal_pengesahan'] ?? now()->toDateString(),
            ]);
            $rpp->meetings()->delete();
            foreach ($data['meetings'] as $index => $meeting) {
                $rpp->meetings()->create(['urutan' => $index + 1, 'isi_kegiatan' => $meeting['isi_kegiatan'], 'tanggal_kbm' => $meeting['tanggal_kbm'] ?? null]);
            }
            $rpp->assessment()->updateOrCreate([], ['pengetahuan' => $data['pengetahuan'], 'keterampilan' => $data['keterampilan'], 'sikap' => $data['sikap']]);
            $rpp->exports()->delete();
        });

        foreach (['pdf', 'png', 'docx'] as $type) {
            GenerateRppExport::dispatch($rpp->id, $type);
        }

        return redirect()->route('guru.rpp.show', $rpp)->with('success', 'RPP berhasil diperbarui.');
    }

    public function destroy(Request $request, Rpp $rpp): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $rpp), 403);
        $rpp->delete();
        return redirect()->route('guru.rpp.index')->with('success', 'RPP dipindahkan ke sampah.');
    }

    public function trash(Request $request): View
    {
        $teacher = $this->teacher($request);
        $rpps = Rpp::onlyTrashed()->with(['classSubject.subject', 'classSubject.classroomTerm'])->where('teacher_id', $teacher->id)->latest('deleted_at')->paginate(15);
        return view('guru.rpp.trash', compact('rpps'));
    }

    public function restore(Request $request, int $rpp): RedirectResponse
    {
        $record = Rpp::onlyTrashed()->findOrFail($rpp);
        abort_unless($request->user()->can('restore', $record), 403);
        $record->restore();
        return redirect()->route('guru.rpp.trash')->with('success', 'RPP dipulihkan.');
    }

    public function references(Request $request): View
    {
        $teacher = $this->teacher($request);
        $ids = $this->access->classSubjectOptions($request->user())->pluck('id');
        $rpps = Rpp::query()->with(['classSubject.subject', 'classSubject.classroomTerm', 'teacher'])
            ->whereIn('diniyyah_class_subject_id', $ids)->where('teacher_id', '!=', $teacher->id)
            ->when($request->filled('q'), fn ($query) => $query->where('materi', 'like', '%'.$request->string('q')->trim().'%'))
            ->latest()->paginate(15)->withQueryString();
        return view('guru.rpp.references', compact('rpps'));
    }

    public function duplicate(Request $request, Rpp $rpp): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($rpp->isStructured() && $rpp->teacher_id !== $teacher->id, 422, 'RPP ini tidak dapat diduplikasi.');
        $assignment = $this->access->assignmentFor($request->user(), $rpp->diniyyah_class_subject_id);
        $rpp->load(['meetings', 'assessment']);

        $copy = DB::transaction(function () use ($rpp, $assignment, $request): Rpp {
            $copy = $rpp->replicate(['legacy_source_id', 'legacy_status', 'legacy_metadata']);
            $copy->fill(['teacher_id' => $assignment->teacher_id, 'diniyyah_teacher_assignment_id' => $assignment->id, 'created_by' => $request->user()->id, 'input_method' => 'manual', 'ai_assisted' => false, 'tanggal_pengesahan' => now()->toDateString()]);
            $copy->save();
            foreach ($rpp->meetings as $meeting) { $copy->meetings()->create($meeting->only(['urutan', 'isi_kegiatan', 'tanggal_kbm'])); }
            if ($rpp->assessment) { $copy->assessment()->create($rpp->assessment->only(['pengetahuan', 'keterampilan', 'sikap'])); }
            return $copy;
        });

        return redirect()->route('guru.rpp.edit', $copy)->with('success', 'RPP referensi disalin. Silakan sesuaikan sebelum digunakan.');
    }

    public function aiDraft(Request $request, RppAiService $ai): RedirectResponse
    {
        $this->teacher($request);
        $request->validate(['foto_materi' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $request->session()->put('rpp.ai_draft', $ai->draftFromImage($request->file('foto_materi')));
        return redirect()->route('guru.rpp.create')->with('success', 'Draft AI siap ditinjau dan disimpan.');
    }

    public function requestHelp(Request $request): RedirectResponse
    {
        $this->teacher($request);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $user = $request->user();
        $body = "Bantuan RPP dari {$user->name}: {$data['message']}";
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->dispatchToRole('admin', 'Permintaan bantuan RPP', $body, 'rpp_help', route('guru.rpp.index'), 'info');
        $dispatcher->dispatchToRole('kabag_diniyyah', 'Permintaan bantuan RPP', $body, 'rpp_help', route('guru.rpp.index'), 'info');
        return redirect()->route('guru.rpp.index')->with('success', 'Permintaan bantuan sudah dikirim ke Admin dan Kabag Diniyyah.');
    }

    public function downloadExport(Request $request, Rpp $rpp, string $type)
    {
        abort_unless($request->user()->can('view', $rpp), 403);
        $export = app(RppExportService::class)->export($rpp, $type);
        return Storage::disk($export->disk)->download($export->path, $this->filename($rpp, $type), ['Content-Type' => $export->mime_type]);
    }

    public function downloadFile(Request $request, Rpp $rpp, RppFile $file)
    {
        abort_unless($request->user()->can('view', $rpp), 403);
        abort_unless($file->rpp_id === $rpp->id, 404);
        return Storage::disk($file->disk)->download($file->path, $file->nama_file, ['Content-Type' => $file->mime_type]);
    }

    public function share(Request $request, Rpp $rpp, string $type): RedirectResponse
    {
        abort_unless($request->user()->can('view', $rpp), 403);
        $export = app(RppExportService::class)->export($rpp, $type);
        $url = URL::temporarySignedRoute('rpp.shared-download', now()->addMinutes((int) config('rpp.share_minutes', 60)), ['export' => $export->id]);
        $message = "Assalamu'alaikum,\nRPP \"{$rpp->materi}\"\n\nUnduh dokumen: {$url}";
        return redirect()->away('https://wa.me/?text='.rawurlencode($message));
    }

    public function sharedDownload(RppExport $export)
    {
        return Storage::disk($export->disk)->download($export->path, 'RPP-'.$export->rpp_id.'.'.$export->type, ['Content-Type' => $export->mime_type]);
    }

    public function promes(Request $request): View
    {
        $this->teacher($request);
        $subjects = $this->access->classSubjectOptions($request->user())->load(['subject', 'classroomTerm', 'rppPromes']);
        return view('guru.rpp.promes', compact('subjects'));
    }

    private function teacher(Request $request)
    {
        abort_unless($request->user()->hasRole('guru') && $request->user()->teacher, 403, 'Akses RPP hanya untuk guru dengan profil aktif.');
        return $request->user()->teacher;
    }

    private function filename(Rpp $rpp, string $type): string
    {
        return 'rpp-'.str($rpp->materi)->slug()->limit(80, '')->toString().'.'.$type;
    }
}
