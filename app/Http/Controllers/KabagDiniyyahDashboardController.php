<?php

namespace App\Http\Controllers;

use App\Models\DiniyyahAssessmentSet;
use App\Models\DiniyyahTeacherAssignment;
use App\Models\Rpp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KabagDiniyyahDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'kabag_diniyyah']), 403);

        return view('kabag.diniyyah.dashboard', [
            'summary' => [
                'submitted_assessments' => DiniyyahAssessmentSet::query()->where('status', 'submitted')->count(),
                'revision_assessments' => DiniyyahAssessmentSet::query()->where('status', 'needs_revision')->count(),
                'active_assignments' => DiniyyahTeacherAssignment::query()->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))->count(),
                'rpps' => Rpp::query()->count(),
            ],
        ]);
    }
}
