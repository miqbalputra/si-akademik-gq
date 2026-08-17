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
            class="fixed inset-x-4 bottom-4 z-[90] mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-xl shadow-slate-950/15 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            role="status"
            aria-live="polite"
            data-journal-overdue-banner
            @unless($isSnoozed) hidden @endunless
        >
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-base font-black text-white" aria-hidden="true">!</span>
                <p class="text-sm font-semibold leading-5 text-amber-950">
                    <span class="font-black">{{ $journalOverdueReminder['count'] }} jurnal masih kosong.</span>
                    Ingatkan lagi pukul <span class="font-black" data-journal-overdue-next-time>{{ $journalOverdueReminder['snoozed_until_label'] ?? '—' }}</span> WIB.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex shrink-0 items-center justify-center rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-black text-amber-900 transition-colors hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
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
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" aria-hidden="true"></div>

            <section
                class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-[1.75rem] border border-rose-200 bg-white shadow-2xl shadow-slate-950/30"
                role="dialog"
                aria-modal="true"
                aria-labelledby="journal-overdue-reminder-title"
                aria-describedby="journal-overdue-reminder-description"
                tabindex="-1"
                data-journal-overdue-dialog
            >
                <header class="border-b border-rose-100 bg-rose-50 px-5 py-5 sm:px-7">
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-xl font-black text-white" aria-hidden="true">!</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-black uppercase tracking-[.16em] text-rose-700">Jurnal perlu dilengkapi</p>
                            <h2 id="journal-overdue-reminder-title" class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">
                                Masih ada {{ $journalOverdueReminder['count'] }} jurnal kosong
                            </h2>
                            <p id="journal-overdue-reminder-description" class="mt-2 text-sm font-medium leading-6 text-slate-600">
                                Lengkapi jurnal tertunda pada {{ $journalOverdueReminder['class_count'] }} kelas di semester {{ $journalOverdueReminder['term_label'] }}. Anda dapat menutup pengingat ini selama tiga jam dan membukanya kembali dari banner.
                            </p>
                        </div>
                    </div>
                </header>

                <div class="min-h-0 overflow-y-auto px-5 py-5 sm:px-7">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-500">Daftar jurnal kosong</p>
                    <ul class="space-y-3" aria-label="Daftar jurnal kosong">
                        @foreach($journalOverdueReminder['empty_slots'] as $slot)
                            @php
                                $timeLabel = $slot['starts_at']
                                    ? \Carbon\Carbon::parse($slot['starts_at'])->format('H:i').' – '.\Carbon\Carbon::parse($slot['ends_at'])->format('H:i')
                                    : null;
                            @endphp
                            <li class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div class="shrink-0 rounded-xl border border-rose-100 bg-white px-3 py-2 text-center sm:min-w-28">
                                        <p class="text-sm font-black text-slate-900">{{ $slot['session_label'] }}</p>
                                        @if($timeLabel)
                                            <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $timeLabel }}</p>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-black text-slate-900">{{ $slot['subject_name'] }}</p>
                                        <p class="mt-1 text-xs font-medium text-slate-600">{{ $slot['date_label'] }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">Kelas: {{ $slot['classroom_names'] }}</p>
                                    </div>
                                    <a
                                        href="{{ $slot['fill_url'] }}"
                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl {{ $slot['is_tafsir'] ? 'bg-cyan-600 hover:bg-cyan-700 focus-visible:outline-cyan-600' : 'bg-teal-600 hover:bg-teal-700 focus-visible:outline-teal-600' }} px-4 py-2.5 text-sm font-black text-white transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                                    >
                                        {{ $slot['is_tafsir'] ? 'Isi Jurnal Tafsir' : 'Isi Jurnal' }}
                                        <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <footer class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <p class="text-xs font-medium leading-5 text-slate-500">Pengingat muncul kembali saat membuka portal setelah masa tunda berakhir.</p>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-black text-slate-700 transition-colors hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                        data-journal-overdue-snooze
                    >
                        Tutup sementara 3 jam
                    </button>
                </footer>
                <p class="hidden px-5 pb-4 text-sm font-semibold text-rose-700 sm:px-7" role="alert" data-journal-overdue-error></p>
            </section>
        </div>
    </div>
@endif
