<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceRedirectService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceSelectionController extends Controller
{
    public function create(Request $request, WorkspaceRedirectService $workspaces): View|RedirectResponse
    {
        $available = $workspaces->availableFor($request->user());

        if (count($available) <= 1) {
            return redirect()->to($workspaces->defaultDestination($request->user()));
        }

        return view('auth.workspace-choice', ['workspaces' => $available]);
    }

    public function store(Request $request, WorkspaceRedirectService $workspaces): RedirectResponse
    {
        $available = $workspaces->availableFor($request->user());
        abort_unless(count($available) > 1, 403);

        $validated = $request->validate([
            'workspace' => ['required', 'string', Rule::in(array_keys($available))],
        ]);

        return redirect()->to($available[$validated['workspace']]['destination']);
    }
}
