<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceRedirectService;
use App\Support\AdminNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceTransitionController extends Controller
{
    public function openPanel(Request $request, AdminNavigation $navigation, WorkspaceRedirectService $workspaces): RedirectResponse
    {
        $data = $request->validate(['destination' => ['required', 'string', Rule::in(array_keys($navigation->definitions()))]]);
        $user = $request->user('web');
        abort_unless($navigation->canAccess($data['destination'], $user), 403);
        $url = $navigation->url($data['destination']);
        $workspaces->switchToAdminGuard($request, $user);
        $request->session()->forget('url.intended');

        return redirect()->to($url);
    }

    public function openPortal(Request $request, WorkspaceRedirectService $workspaces): RedirectResponse
    {
        $user = $request->user('admin');
        $available = array_diff(array_keys($workspaces->availableFor($user)), [WorkspaceRedirectService::MANAGEMENT]);
        $data = $request->validate(['workspace' => ['required', 'string', Rule::in($available)]]);
        $url = $workspaces->destinationFor($user, $data['workspace']);
        $workspaces->switchToPortalGuard($request, $user);
        $request->session()->forget('url.intended');

        return redirect()->to($url);
    }
}
