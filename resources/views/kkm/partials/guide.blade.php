<div class="flex flex-wrap items-center gap-3" x-data>
    <button type="button" class="btn" aria-haspopup="dialog" aria-controls="kkm-guide-dialog" @click="$refs.guide.showModal()">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v14m0-14C9.5 4.5 5.5 4 3 5v14c2.5-1 6.5-.5 9 2m0-14c2.5-2.5 6.5-3 9-2v14c-2.5-1-6.5-.5-9 2" /></svg>
        Lihat Panduan
    </button>

    @if($videoUrl ?? null)
        <a class="btn" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Lihat Video panduan KKM di YouTube (tab baru)">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="4" /><path stroke-linecap="round" stroke-linejoin="round" d="m10 9 5 3-5 3V9Z" /></svg>
            Lihat Video
            <span class="sr-only">(YouTube, tab baru)</span>
        </a>
    @endif

    <dialog id="kkm-guide-dialog" x-ref="guide" class="kkm-guide-modal rounded-2xl border border-line bg-surface p-0 text-body shadow-2xl" aria-labelledby="kkm-guide-title" aria-describedby="kkm-guide-description">
        <div class="kkm-guide-layout">
            <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                <div>
                    <h2 id="kkm-guide-title" class="ui-card-title text-heading">Panduan Pengisian KKM</h2>
                    <p id="kkm-guide-description" class="mt-1 text-theme-sm text-muted">Kenali setiap unsur sebelum menentukan angka dan alasan penilaiannya.</p>
                </div>
                <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-muted hover:bg-surface-subtle" aria-label="Tutup panduan KKM" autofocus @click="$refs.guide.close()">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </header>

            <div class="space-y-5 overflow-y-auto overscroll-contain px-5 py-5 text-theme-sm leading-relaxed sm:px-6">
                <p class="rounded-xl border border-line bg-surface-subtle p-4">Isi <strong>angka 60–100</strong> untuk setiap unsur sesuai kondisi pembelajaran. Kategori muncul otomatis. Tuliskan alasan yang mendukung angka tersebut sebelum mengajukan KKM untuk ACC.</p>

                <section class="rounded-xl border border-line p-4 sm:p-5" aria-labelledby="kkm-guide-complexity">
                    <h3 id="kkm-guide-complexity" class="font-semibold text-heading">A. Kompleksitas Materi</h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-info-soft p-4">
                            <p>Menggambarkan tingkat kesulitan materi pelajaran.</p>
                            <ul class="mt-3 space-y-1">
                                <li>Materi mudah → nilai tinggi</li>
                                <li>Materi sedang → nilai sedang</li>
                                <li>Materi sulit → nilai rendah</li>
                            </ul>
                        </div>
                        <table class="w-full self-start text-left">
                            <caption class="sr-only">Kategori kompleksitas dan rentang nilai</caption>
                            <thead><tr class="border-b border-line"><th scope="col" class="p-2">Kategori Kompleksitas</th><th scope="col" class="whitespace-nowrap p-2">Rentang Nilai</th></tr></thead>
                            <tbody><tr class="border-b border-line"><td class="p-2">Rendah</td><td class="p-2">80–100</td></tr><tr class="border-b border-line"><td class="p-2">Sedang</td><td class="p-2">66–79</td></tr><tr><td class="p-2">Tinggi</td><td class="p-2">60–65</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-line p-4 sm:p-5" aria-labelledby="kkm-guide-support">
                    <h3 id="kkm-guide-support" class="font-semibold text-heading">B. Daya Dukung Satuan Pendidikan</h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-success-soft p-4">
                            <p>Meliputi:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li>Kualifikasi guru</li>
                                <li>Sarana dan prasarana</li>
                                <li>Media pembelajaran</li>
                                <li>Lingkungan belajar</li>
                                <li>Jumlah peserta didik dalam satu kelas</li>
                                <li>Jumlah jam pembelajaran</li>
                                <li>Dan lain-lain</li>
                            </ul>
                        </div>
                        <table class="w-full self-start text-left">
                            <caption class="sr-only">Kategori daya dukung dan rentang nilai</caption>
                            <thead><tr class="border-b border-line"><th scope="col" class="p-2">Kategori Daya Dukung</th><th scope="col" class="whitespace-nowrap p-2">Rentang Nilai</th></tr></thead>
                            <tbody><tr class="border-b border-line"><td class="p-2">Kurang Mendukung</td><td class="p-2">60–65</td></tr><tr class="border-b border-line"><td class="p-2">Cukup Mendukung</td><td class="p-2">66–79</td></tr><tr><td class="p-2">Sangat Mendukung</td><td class="p-2">80–100</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-line p-4 sm:p-5" aria-labelledby="kkm-guide-intake">
                    <h3 id="kkm-guide-intake" class="font-semibold text-heading">C. Karakteristik Peserta Didik (Intake) / Daya Serap Siswa</h3>
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-danger-soft p-4">
                            <p>Kemampuan awal atau rata-rata kemampuan peserta didik.</p>
                            <p class="mt-3">Bisa dilihat dari:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li>Kondisi lingkungan mayoritas siswa</li>
                                <li>Hasil tes masuk saat PPDB untuk kelas 1</li>
                                <li>Nilai rapor atau hasil tes sebelumnya</li>
                            </ul>
                        </div>
                        <table class="w-full self-start text-left">
                            <caption class="sr-only">Kategori intake dan rentang nilai</caption>
                            <thead><tr class="border-b border-line"><th scope="col" class="p-2">Kategori Intake</th><th scope="col" class="whitespace-nowrap p-2">Rentang Nilai</th></tr></thead>
                            <tbody><tr class="border-b border-line"><td class="p-2">Rendah</td><td class="p-2">60–65</td></tr><tr class="border-b border-line"><td class="p-2">Sedang</td><td class="p-2">66–79</td></tr><tr><td class="p-2">Tinggi</td><td class="p-2">80–100</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-line bg-surface-subtle p-4 sm:p-5" aria-labelledby="kkm-guide-calculation">
                    <h3 id="kkm-guide-calculation" class="font-semibold text-heading">Ketentuan Perhitungan KKM</h3>
                    <p class="mt-2">Rata-rata = (Kompleksitas + Daya Dukung + Intake) ÷ 3.</p>
                    <p class="mt-2">Rata-rata tidak dibulatkan terlebih dahulu. KKM akhir mengikuti kategori berikut:</p>
                    <table class="mt-3 w-full text-left">
                        <caption class="sr-only">Ketentuan nilai akhir KKM sekolah</caption>
                        <thead><tr class="border-b border-line"><th scope="col" class="p-2">Rata-rata</th><th scope="col" class="p-2">KKM Akhir</th></tr></thead>
                        <tbody><tr class="border-b border-line"><td class="p-2">Kurang dari 70</td><td class="p-2 font-semibold">65</td></tr><tr class="border-b border-line"><td class="p-2">70 sampai kurang dari 75</td><td class="p-2 font-semibold">70</td></tr><tr><td class="p-2">75 ke atas</td><td class="p-2 font-semibold">75</td></tr></tbody>
                    </table>
                    <p class="mt-3 text-muted">Contoh: (70 + 70 + 69) ÷ 3 = 69,67 → KKM 65; (75 + 75 + 74) ÷ 3 = 74,67 → KKM 70; (80 + 75 + 70) ÷ 3 = 75 → KKM 75. KKM resmi berlaku setelah disetujui.</p>
                </section>
            </div>

            <footer class="flex justify-end border-t border-line px-5 py-4 sm:px-6">
                <button type="button" class="btn btn-primary" @click="$refs.guide.close()">Tutup</button>
            </footer>
        </div>
    </dialog>
</div>
