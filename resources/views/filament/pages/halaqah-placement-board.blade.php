<x-filament-panels::page>
    @if (! $hasTerm)
        <x-filament::section>
            <p style="color:var(--gray-600);font-size:14px;">
                Pilih <strong>Periode Akademik</strong> dulu untuk mulai menempatkan santri ke halaqah. Bila belum ada periode, buat dulu di menu <em>Data Sekolah → Periode Akademik</em>.
            </p>
        </x-filament::section>
    @endif

    {{-- Baris kontrol --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px;">
        <div style="display:flex;flex-direction:column;gap:4px;min-width:240px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);">Periode Akademik</label>
            <select wire:model.live="academicTermId" style="border:1.5px solid var(--gray-200);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:500;background:var(--gray-50);">
                @foreach ($academicTerms as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:220px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);">Cari (Nama / NIS)</label>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ketik nama atau NIS untuk menyaring…" style="border:1.5px solid var(--gray-200);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:500;background:var(--gray-50);">
        </div>

        <div style="display:flex;flex-direction:column;gap:4px;min-width:140px;">
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-500);">Jenis Kelamin</label>
            <select wire:model.live="gender" style="border:1.5px solid var(--gray-200);border-radius:10px;padding:9px 12px;font-size:14px;font-weight:500;background:var(--gray-50);">
                <option value="">Semua</option>
                <option value="male">Laki-laki</option>
                <option value="female">Perempuan</option>
            </select>
        </div>
    </div>

    @if ($hasTerm)
        <p style="font-size:13px;color:var(--gray-500);margin-bottom:10px;">
            Tarik kartu santri ke kolom halaqah. Memindahkan ke halaqah lain akan menandai keanggotaan lama sebagai <em>Pindah</em> (riwayat tetap). Kartu menampilkan <strong>NIS</strong> &amp; <strong>kelas saat ini</strong> untuk membedakan nama yang sama.
        </p>

        <div x-data="placementBoard()" x-init="init()" @board-refresh.window="$nextTick(() => init())" style="overflow-x:auto;padding-bottom:12px;">
            <div style="display:flex;gap:14px;align-items:flex-start;min-height:300px;">
                {{-- Kolom Belum Dihalaqah --}}
                <div style="flex:0 0 260px;border:1.5px dashed var(--gray-200);border-radius:14px;background:var(--gray-50);padding:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <strong style="font-size:13px;color:var(--gray-700);">Belum Dihalaqah</strong>
                        <span style="font-size:11px;background:var(--gray-200);color:var(--gray-700);border-radius:999px;padding:2px 8px;">{{ $unassigned->count() }}</span>
                    </div>
                    <div class="board-col-list" data-target-id="" style="display:flex;flex-direction:column;gap:8px;min-height:120px;">
                        @foreach ($unassigned as $card)
                            @include('filament.pages.partials.placement-card', ['card' => $card, 'key' => 'hp-unassigned-'.$card['id']])
                        @endforeach
                    </div>
                </div>

                {{-- Kolom per halaqah --}}
                @foreach ($halaqahs as $halaqah)
                    <div style="flex:0 0 260px;border:1.5px solid var(--gray-200);border-radius:14px;background:#fff;padding:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <strong style="font-size:13px;color:var(--gray-700);" title="{{ $halaqah->name }}">{{ \Illuminate\Support\Str::limit($halaqah->name, 22) }}</strong>
                            <span style="font-size:11px;background:var(--amber-100,#fef3c7);color:var(--amber-800,#92400e);border-radius:999px;padding:2px 8px;">{{ ($placed[$halaqah->id] ?? collect())->count() }}</span>
                        </div>
                        <div class="board-col-list" data-target-id="{{ $halaqah->id }}" style="display:flex;flex-direction:column;gap:8px;min-height:120px;">
                            @foreach (($placed[$halaqah->id] ?? collect()) as $card)
                                @include('filament.pages.partials.placement-card', ['card' => $card, 'key' => 'hp-'.$halaqah->id.'-'.$card['id']])
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($halaqahs->isEmpty())
                    <div style="padding:24px;color:var(--gray-500);font-size:14px;">
                        Belum ada halaqah pada periode ini. Buat dulu di menu <em>Tahfidz → Halaqah</em>.
                    </div>
                @endif
            </div>
        </div>
    @endif

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <script>
            function placementBoard() {
                return {
                    instances: [],
                    init() {
                        this.destroy();
                        const lists = this.$el.querySelectorAll('.board-col-list');
                        lists.forEach((el) => {
                            this.instances.push(Sortable.create(el, {
                                group: 'students',
                                animation: 150,
                                ghostClass: 'placement-ghost',
                                chosenClass: 'placement-chosen',
                                onEnd: (evt) => {
                                    const studentId = Number(evt.item.dataset.studentId);
                                    const target = evt.to.dataset.targetId;
                                    const targetId = target === '' ? null : Number(target);
                                    this.$wire.assignToHalaqah(studentId, targetId);
                                },
                            }));
                        });
                    },
                    destroy() {
                        (this.instances || []).forEach((s) => s.destroy());
                        this.instances = [];
                    },
                };
            }
        </script>
        <style>
            .placement-ghost { opacity: .4; }
            .placement-chosen { box-shadow: 0 6px 18px rgba(0,0,0,.18); }
            .board-col-list > .placement-card { cursor: grab; }
            .board-col-list > .placement-card:active { cursor: grabbing; }
        </style>
    @endpush
</x-filament-panels::page>