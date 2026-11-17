<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\ClassroomTerm;
use App\Services\ClassRankingReport;
use App\Services\Exports\ClassRankingExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AdminClassRankingController extends Controller
{
    private function report(Request $request): array
    {
        abort_unless($request->user('admin')?->hasRole('admin'), 403);
        $filters = $request->validate(['academic_term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'classroom_term_id' => ['nullable', 'integer', 'min:1'], 'include_archived' => ['nullable', 'boolean']]);
        $terms = AcademicTerm::with('academicYear')->orderByDesc('is_active')->orderByDesc('id')->get();
        $term = isset($filters['academic_term_id']) ? $terms->firstWhere('id', $filters['academic_term_id']) : $terms->first();
        $classrooms = $term ? ClassroomTerm::where('academic_term_id', $term->id)->orderBy('name')->get() : collect();
        $selectedClassId = isset($filters['classroom_term_id']) ? (int) $filters['classroom_term_id'] : null;
        abort_if($selectedClassId && ! $classrooms->contains('id', $selectedClassId), 404);
        $includeArchived = $request->boolean('include_archived');
        $report = $term ? app(ClassRankingReport::class)->build($term, $selectedClassId, $includeArchived) : null;

        return compact('terms', 'term', 'classrooms', 'selectedClassId', 'includeArchived', 'report');
    }

    public function index(Request $request)
    {
        return view('admin.class-rankings.index', $this->report($request));
    }

    public function export(Request $request, string $format, ClassRankingExporter $exporter)
    {
        $data = $this->report($request);
        $report = $data['report'];
        abort_unless($report && count($report['classes']), 422, 'Belum ada kelas pada periode yang dipilih.');
        $scope = $data['selectedClassId'] ? $report['classes'][0]['name'] : 'Semua Kelas';
        $filename = 'Peringkat-'.substr(str()->slug($report['term']->academicYear?->name.'-'.$report['term']->name.'-'.$scope), 0, 160);
        if ($format === 'xlsx') {
            $content = $exporter->export($report);
            $response = response()->streamDownload(fn () => print ($content), $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
        } else {
            $response = Pdf::loadView('admin.class-rankings.pdf', compact('report'))->setPaper([0, 0, 612, 936], 'landscape')
                ->download($filename.'.pdf')->withHeaders(['Cache-Control' => 'private, no-store']);
        }
        activity('report_cards')->causedBy($request->user('admin'))->withProperties([
            'academic_term_id' => $report['term']->id, 'classroom_term_id' => $data['selectedClassId'], 'include_archived' => $data['includeArchived'], 'format' => $format,
        ])->log('class_rankings_exported');

        return $response;
    }
}
