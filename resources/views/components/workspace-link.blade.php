@props(['href'])
@php
    $transition = null;
    $allowed = true;
    $user = auth()->user();
    if ($user && auth()->getDefaultDriver() !== 'admin') {
        $navigation = app(\App\Support\AdminNavigation::class);
        $destination = $navigation->keyForUrl($href);
        if ($destination !== null) {
            $allowed = $navigation->canAccess($destination, $user);
            $transition = ['url' => route('workspace.open-panel'), 'field' => 'destination', 'value' => $destination];
        }
    } elseif ($user) {
        foreach (app(\App\Services\WorkspaceRedirectService::class)->availableFor($user) as $key => $workspace) {
            if ($key !== \App\Services\WorkspaceRedirectService::MANAGEMENT && $workspace['destination'] === $href) {
                $transition = ['url' => route('workspace.open-portal'), 'field' => 'workspace', 'value' => $key];
                break;
            }
        }
    }
@endphp
@if($allowed)
    @if($transition)
        <form method="POST" action="{{ $transition['url'] }}" style="display:contents" data-workspace-transition>
            @csrf
            <input type="hidden" name="{{ $transition['field'] }}" value="{{ $transition['value'] }}">
            <button type="submit" {{ $attributes->class(['w-full min-h-11 text-start']) }}>{{ $slot }}</button>
        </form>
    @else
        <a href="{{ $href }}" {{ $attributes }}>{{ $slot }}</a>
    @endif
@endif
