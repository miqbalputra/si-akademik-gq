@props(['tasmiWaliReminder' => null])

@if(($tasmiWaliReminder['count'] ?? 0) > 0)
    <div data-tasmi-wali-reminder data-dismiss-url="{{ route('guru.tasmi-wali.reminder.dismiss') }}">
        <aside class="fixed inset-x-4 bottom-4 z-[90] mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-xl shadow-slate-950/15 sm:flex-row sm:items-center sm:justify-between sm:px-5" role="status" aria-live="polite" data-tasmi-wali-banner @if($tasmiWaliReminder['should_show_modal']) hidden @endif>
            <div class="flex items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-base font-black text-white" aria-hidden="true">✓</span><p class="text-sm font-semibold leading-5 text-school-800"><span class="font-black">{{ $tasmiWaliReminder['count'] }} hasil Tasmi' belum dibuka.</span> Lihat hasil santri di kelas Anda.</p></div>
            <a href="{{ route('guru.tasmi-wali.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-black text-emerald-900 transition-colors hover:bg-emerald-50">Buka hasil Tasmi'</a>
        </aside>
        @if($tasmiWaliReminder['should_show_modal'])
        <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" data-tasmi-wali-modal>
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" aria-hidden="true"></div>
            <section class="relative flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-[1.75rem] border border-emerald-200 bg-white shadow-2xl shadow-slate-950/30" role="dialog" aria-modal="true" aria-labelledby="tasmi-wali-reminder-title" tabindex="-1" data-tasmi-wali-dialog>
                <header class="border-b border-emerald-100 bg-emerald-50 px-5 py-5 sm:px-7"><div class="flex items-start gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-xl font-black text-white" aria-hidden="true">✓</span><div><p class="text-[11px] font-black uppercase tracking-[.16em] text-emerald-700">Hasil Tasmi' baru</p><h2 id="tasmi-wali-reminder-title" class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">Ada {{ $tasmiWaliReminder['new_items']->count() }} hasil yang perlu dilihat</h2><p class="mt-2 text-sm font-medium leading-6 text-slate-600">Hasil baru dari PJ Tasmi' untuk santri di kelas Anda. Anda dapat menutup pengingat ini dan membukanya kembali dari banner.</p></div></div></header>
                <div class="min-h-0 overflow-y-auto px-5 py-5 sm:px-7"><ul class="space-y-3" aria-label="Hasil Tasmi' baru">@foreach($tasmiWaliReminder['new_items'] as $item)<li class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center"><div class="min-w-0 flex-1"><p class="text-sm font-black text-slate-900">{{ $item['student_name'] }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $item['classroom_name'] }} · {{ $item['date_label'] }} · {{ $item['juz_label'] }}</p><span class="mt-2 inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-black text-emerald-800">{{ $item['predicate_label'] }}</span></div><a href="{{ $item['detail_url'] }}" class="btn btn-primary shrink-0">Lihat detail</a></div></li>@endforeach</ul></div>
                <footer class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end sm:px-7"><a href="{{ route('guru.tasmi-wali.index') }}" class="btn btn-outline">Buka semua hasil</a><button type="button" class="btn btn-primary" data-tasmi-wali-dismiss>Tutup sementara</button></footer>
            </section>
        </div>
        @endif
    </div>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const root = document.querySelector('[data-tasmi-wali-reminder]');
                if (!root) return;
                const modal = root.querySelector('[data-tasmi-wali-modal]');
                const banner = root.querySelector('[data-tasmi-wali-banner]');
                const dialog = root.querySelector('[data-tasmi-wali-dialog]');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                if (modal && !modal.hidden) dialog?.focus();
                root.querySelector('[data-tasmi-wali-dismiss]')?.addEventListener('click', async function () {
                    try { const response = await fetch(root.dataset.dismissUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } }); if (!response.ok) throw new Error('Gagal'); } catch (_) { return; }
                    modal.hidden = true; banner.hidden = false;
                });
            });
        </script>
    @endpush
@endif
