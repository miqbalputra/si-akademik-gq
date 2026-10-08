<x-layouts.portal title="Notifikasi" portalLabel="{{ auth()->user()->hasRole('guru') ? 'Portal Guru' : (auth()->user()->hasRole('wali_santri') ? 'Portal Wali Santri' : 'Ruang GQ') }}" breadcrumb="Notifikasi">
    @push('styles')
    <style>
        .notif-item { display:flex; align-items:flex-start; gap:.875rem; border-bottom:1px solid var(--ui-surface-muted); padding:1rem 1.125rem; transition:background-color .15s ease; }
        .notif-item:hover { background:var(--ui-surface-subtle); }
        .notif-item.unread { background:var(--color-brand-25); }
        .notif-icon { display:flex; width:2.25rem; height:2.25rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.625rem; font-size:.875rem; font-weight:700; }
        .badge-info, .icon-info { background:var(--color-info-50); color:var(--color-info-700); }
        .badge-success, .icon-success { background:var(--color-success-50); color:var(--color-success-700); }
        .badge-warning, .icon-warning { background:var(--color-warning-50); color:var(--color-warning-700); }
        .badge-danger, .icon-danger { background:var(--color-danger-50); color:var(--color-danger-700); }
        .badge-slate, .icon-slate { background:var(--ui-surface-muted); color:var(--ui-text); }
    </style>
    @endpush

    <header class="portal-page-header fade-up">
        <div>
            <p class="eyebrow">Pusat informasi</p>
            <h1>Notifikasi</h1>
            <p class="mt-2 text-sm font-normal text-muted dark:text-muted">
                @if($unreadCount > 0)
                    Ada <strong>{{ $unreadCount }}</strong> notifikasi belum dibaca.
                @else
                    Tidak ada notifikasi belum dibaca.
                @endif
            </p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Tandai semua dibaca
                </button>
            </form>
        @endif
    </header>

    @if (session('status'))
        <div class="inline-feedback inline-feedback-success fade-up mb-4" role="status">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card fade-up delay-1 mb-4 p-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_auto_auto] xl:items-end">
            <div>
                <label for="notification-type" class="mb-1.5 block text-xs font-semibold text-body dark:text-body">Tipe</label>
                <select id="notification-type" name="type" class="form-input">
                    <option value="">Semua tipe</option>
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['type'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="notification-severity" class="mb-1.5 block text-xs font-semibold text-body dark:text-body">Tingkat</label>
                <select id="notification-severity" name="severity" class="form-input">
                    <option value="">Semua</option>
                    @foreach($severityOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['severity'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex min-h-10 cursor-pointer items-center gap-2 text-sm font-medium text-body dark:text-body">
                <input type="checkbox" name="unread_only" value="1" @if(($filters['unread_only'] ?? '') === '1') checked @endif class="h-4 w-4 rounded border-line-strong text-brand-ink focus:ring-brand-500">
                Belum dibaca saja
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    <div class="card fade-up delay-2 overflow-hidden">
        @if($notifications->isEmpty())
            <div class="empty-state m-5 border-dashed">
                <svg class="mx-auto mb-3 h-10 w-10 text-soft dark:text-soft" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                <p class="text-sm font-medium text-muted dark:text-muted">Tidak ada notifikasi yang sesuai filter.</p>
            </div>
        @else
            @foreach($notifications as $notif)
                @php
                    $iconClass = match($notif->severity) {
                        'success' => 'icon-success',
                        'warning' => 'icon-warning',
                        'danger' => 'icon-danger',
                        default => 'icon-info',
                    };
                    $badgeClass = match($notif->severity) {
                        'success' => 'badge-success',
                        'warning' => 'badge-warning',
                        'danger' => 'badge-danger',
                        default => 'badge-info',
                    };
                    $iconChar = match($notif->severity) {
                        'success' => '✓',
                        'warning' => '!',
                        'danger' => '!',
                        default => 'i',
                    };
                @endphp
                <div class="notif-item {{ $notif->status === 'unread' ? 'unread' : '' }}">
                    <div class="notif-icon {{ $iconClass }}" aria-hidden="true">{{ $iconChar }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <strong class="text-sm font-semibold text-heading dark:text-white">{{ $notif->title }}</strong>
                            @if($notif->status === 'unread')
                                <span class="badge badge-danger">Baru</span>
                            @endif
                            @if($notif->batch_count > 1)
                                <span class="badge badge-slate">×{{ $notif->batch_count }}</span>
                            @endif
                            <span class="badge {{ $badgeClass }}">{{ $severityOptions[$notif->severity] ?? $notif->severity }}</span>
                        </div>
                        <p class="m-0 mb-1.5 text-sm leading-5 text-body dark:text-body">{{ $notif->body }}</p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-soft dark:text-muted">
                            <span class="font-medium">{{ $notif->created_at?->diffForHumans() }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ $typeOptions[$notif->notification_type] ?? $notif->notification_type }}</span>
                            @if($notif->link_url)
                                <a href="{{ $notif->link_url }}" class="font-semibold text-brand-ink hover:text-brand-ink dark:text-brand-300">Lihat detail <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col gap-1.5">
                        @if($notif->status === 'unread')
                            <form method="POST" action="{{ route('notifications.read', $notif) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm" title="Tandai dibaca">Dibaca</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('notifications.archive', $notif) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus" onclick="return confirm('Hapus notifikasi ini?')">Hapus</button>
                        </form>
                    </div>
                </div>
            @endforeach
            <div class="border-t border-line p-4 dark:border-gray-800">
                {{ $notifications->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>
