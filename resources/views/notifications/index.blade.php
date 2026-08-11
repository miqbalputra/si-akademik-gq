<x-layouts.portal title="Notifikasi" portalLabel="{{ auth()->user()->hasRole('guru') ? 'Portal Guru' : (auth()->user()->hasRole('wali_santri') ? 'Portal Wali Santri' : 'Ruang GQ') }}" breadcrumb="Notifikasi">
    @push('styles')
    <style>
        .card { background: #fff; border: 1px solid #f1f5f9; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .form-input { width:100%; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 13px; font-size:13px; font-weight:500; color:#1e293b; background:#f8fafc; outline:none; font-family:'Outfit',sans-serif; }
        .form-input:focus { border-color:#6b21a8; background:#fff; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; font-weight:700; font-size:12px; border-radius:8px; padding:7px 14px; transition: all .2s; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; }
        .btn-primary { background:#6b21a8; color:#fff; }
        .btn-primary:hover { background:#581c87; }
        .btn-outline { background:transparent; border:1.5px solid #e2e8f0; color:#475569; }
        .btn-outline:hover { background:#f8fafc; }
        .notif-item { display:flex; align-items:flex-start; gap:14px; padding:16px 18px; border-bottom:1px solid #f1f5f9; transition: background .15s; }
        .notif-item:hover { background: #faf5ff; }
        .notif-item.unread { background: #fdfbff; }
        .notif-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:14px; font-weight:800; }
        .icon-info { background:#dbeafe; color:#1e40af; }
        .icon-success { background:#dcfce7; color:#166534; }
        .icon-warning { background:#fef3c7; color:#92400e; }
        .icon-danger { background:#fee2e2; color:#991b1b; }
        .icon-slate { background:#f1f5f9; color:#475569; }
        .empty-state { border: 2px dashed #e2e8f0; border-radius: 16px; padding: 48px 24px; text-align: center; }
    </style>
    @endpush

    <header class="fade-up" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
            <div>
                <h1 style="font-size:24px;font-weight:900;color:#0f172a;margin:0 0 4px;letter-spacing:-.02em;">Notifikasi</h1>
                <p style="font-size:14px;color:#64748b;font-weight:500;margin:0;">
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
                        <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        Tandai semua dibaca
                    </button>
                </form>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div style="margin-bottom:18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px;font-size:13px;font-weight:600;color:#166534;" class="fade-up">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card fade-up delay-1" style="padding:14px;margin-bottom:18px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:flex-end;">
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Tipe</label>
                <select name="type" class="form-input">
                    <option value="">Semua tipe</option>
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['type'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:10px;font-weight:800;text-transform:uppercase;color:#475569;">Severity</label>
                <select name="severity" class="form-input">
                    <option value="">Semua</option>
                    @foreach($severityOptions as $value => $label)
                        <option value="{{ $value }}" @if(($filters['severity'] ?? '') === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:8px;padding-bottom:6px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#475569;cursor:pointer;">
                    <input type="checkbox" name="unread_only" value="1" @if(($filters['unread_only'] ?? '') === '1') checked @endif style="cursor:pointer;">
                    Belum dibaca saja
                </label>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-outline">Reset</a>
            </div>
        </div>
    </form>

    {{-- Daftar --}}
    <div class="card fade-up delay-2">
        @if($notifications->isEmpty())
            <div class="empty-state" style="margin:20px;">
                <svg style="width:40px;height:40px;color:#cbd5e1;margin:0 auto 12px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                <p style="color:#94a3b8;font-weight:600;font-size:14px;">Tidak ada notifikasi yang sesuai filter.</p>
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
                    <div class="notif-icon {{ $iconClass }}">{{ $iconChar }}</div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                            <strong style="font-size:14px;color:#0f172a;">{{ $notif->title }}</strong>
                            @if($notif->status === 'unread')
                                <span class="badge badge-danger">Baru</span>
                            @endif
                            @if($notif->batch_count > 1)
                                <span class="badge badge-slate">×{{ $notif->batch_count }}</span>
                            @endif
                            <span class="badge {{ $badgeClass }}">{{ $severityOptions[$notif->severity] ?? $notif->severity }}</span>
                        </div>
                        <p style="font-size:13px;color:#475569;margin:0 0 6px;line-height:1.5;">{{ $notif->body }}</p>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span style="font-size:11px;color:#94a3b8;font-weight:600;">{{ $notif->created_at?->diffForHumans() }}</span>
                            <span style="font-size:11px;color:#94a3b8;">·</span>
                            <span style="font-size:11px;color:#94a3b8;font-weight:500;">{{ $typeOptions[$notif->notification_type] ?? $notif->notification_type }}</span>
                            @if($notif->link_url)
                                <a href="{{ $notif->link_url }}" style="font-size:11px;font-weight:700;color:#6b21a8;text-decoration:none;">Lihat detail →</a>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                        @if($notif->status === 'unread')
                            <form method="POST" action="{{ route('notifications.read', $notif) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline" style="font-size:10px;padding:4px 10px;" title="Tandai dibaca">✓ Dibaca</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('notifications.archive', $notif) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline" style="font-size:10px;padding:4px 10px;color:#991b1b;border-color:#fecaca;" title="Hapus" onclick="return confirm('Hapus notifikasi ini?')">×</button>
                        </form>
                    </div>
                </div>
            @endforeach
            <div style="padding:14px 18px;">
                {{ $notifications->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>
