@props(['journalOverdueReminder' => null])

@if(($journalOverdueReminder['count'] ?? 0) > 0)
    @php
        $isSnoozed = (bool) ($journalOverdueReminder['is_snoozed'] ?? false);
    @endphp

    <div
        data-journal-overdue-reminder
        data-snooze-url="{{ route('guru.journal-reminder.snooze') }}"
    >
        <aside
            class="fixed inset-x-4 bottom-4 z-30 mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-brand-line bg-brand-soft px-4 py-3 shadow-xl shadow-gray-950/15 sm:flex-row sm:items-center sm:justify-between sm:px-5 xl:start-[19rem]"
            role="status"
            aria-live="polite"
            data-journal-overdue-banner
            @unless($isSnoozed) hidden @endunless
        >
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-neon text-base font-semibold text-neon-ink" aria-hidden="true">!</span>
                <p class="text-theme-sm font-normal text-school-800">
                    <span class="font-semibold">{{ $journalOverdueReminder['count'] }} jurnal masih kosong.</span>
                    Ingatkan lagi pukul <span class="font-semibold" data-journal-overdue-next-time>{{ $journalOverdueReminder['snoozed_until_label'] ?? '—' }}</span> WIB.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-brand-line bg-surface px-4 py-2 text-school-800 transition-colors hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neon text-theme-sm font-medium"
                data-journal-overdue-open
            >
                Buka daftar jurnal
            </button>
        </aside>

        <div
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
            data-journal-overdue-modal
            @if($isSnoozed) hidden @endif
        >
            <div class="absolute inset-0 bg-gray-950/55 backdrop-blur-sm" aria-hidden="true"></div>

            <section
                class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-danger-line bg-surface shadow-2xl shadow-gray-950/30"
                role="dialog"
                aria-modal="true"
                aria-labelledby="journal-overdue-reminder-title"
                aria-describedby="journal-overdue-reminder-description"
                tabindex="-1"
                data-journal-overdue-dialog
            >
                <header class="border-b border-danger-line bg-danger-soft px-5 py-5 sm:px-7">
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-danger-600 text-xl font-semibold text-white" aria-hidden="true">!</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-theme-xs font-normal uppercase text-danger-ink">Jurnal perlu dilengkapi</p>
                            <h2 id="journal-overdue-reminder-title" class="mt-1 text-heading ui-modal-title">
                                Masih ada {{ $journalOverdueReminder['count'] }} jurnal kosong
                            </h2>
                            <p id="journal-overdue-reminder-description" class="mt-2 ui-modal-copy text-body">
                                Lengkapi jurnal tertunda pada {{ $journalOverdueReminder['class_count'] }} kelas di semester {{ $journalOverdueReminder['term_label'] }}. Anda dapat menutup pengingat ini selama tiga jam dan membukanya kembali dari banner.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="min-h-0 overflow-y-auto px-5 py-5 sm:px-7">
                    <p class="mb-3 text-theme-xs font-normal uppercase text-muted">Daftar jurnal kosong</p>
                    <ul class="space-y-3" aria-label="Daftar jurnal kosong">
                        @foreach($journalOverdueReminder['empty_slots'] as $slot)
                            @php
                                $timeLabel = $slot['starts_at']
                                    ? \Carbon\Carbon::parse($slot['starts_at'])->format('H:i').' – '.\Carbon\Carbon::parse($slot['ends_at'])->format('H:i')
                                    : null;
                            @endphp
                            <li class="rounded-2xl border border-line bg-surface-subtle p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div class="shrink-0 rounded-xl border border-danger-line bg-surface px-3 py-2 text-center sm:min-w-28">
                                        <p class="text-theme-sm font-medium text-heading">{{ $slot['session_label'] }}</p>
                                        @if($timeLabel)
                                            <p class="mt-0.5 text-theme-xs font-normal text-muted">{{ $timeLabel }}</p>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-theme-sm font-medium text-heading">{{ $slot['subject_name'] }}</p>
                                        <p class="mt-1 text-theme-xs font-normal text-body">{{ $slot['date_label'] }}</p>
                                        <p class="mt-1 text-theme-xs font-normal text-muted">Kelas: {{ $slot['classroom_names'] }}</p>
                                    </div>
                                    <a
                                        href="{{ $slot['fill_url'] }}"
                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl {{ $slot['is_tafsir'] ? 'bg-info-600 hover:bg-info-700 focus-visible:outline-info-600' : 'bg-info-600 hover:bg-info-700 focus-visible:outline-info-600' }} px-4 py-2.5 text-white transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 text-theme-sm font-medium"
                                    >
                                        {{ $slot['is_tafsir'] ? 'Isi Jurnal Tafsir' : 'Isi Jurnal' }}
                                        <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <footer class="flex flex-col gap-3 border-t border-line bg-surface-subtle px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <p class="min-w-0 text-theme-xs font-normal text-muted">Pengingat muncul kembali saat membuka portal setelah masa tunda berakhir.</p>
                    <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-theme-sm font-medium text-body transition-colors hover:border-danger-line hover:bg-danger-soft hover:text-danger-ink">Keluar</button>
                        </form>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-line-strong bg-surface px-4 py-2.5 text-body transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500 text-theme-sm font-medium"
                            data-journal-overdue-snooze
                        >
                            Tutup sementara 3 jam
                        </button>
                    </div>
                </footer>
                <p class="hidden px-5 pb-4 text-theme-sm font-normal text-danger-ink sm:px-7" role="alert" data-journal-overdue-error></p>
            </section>
        </div>
    </div>
@endif
