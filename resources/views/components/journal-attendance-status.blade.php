@props(['eligibility'])

<div class="mb-5 rounded-xl border p-4 text-theme-sm"
     x-data="{
        eligibility: @js($eligibility),
        checking: false,
        async refresh() {
            this.checking = true;
            this.$dispatch('journal-attendance-checked', { allowed: false });
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('attendance_check', '1');
                const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Presensi belum dapat diperiksa');
                const decision = await response.json();
                if (typeof decision.allowed !== 'boolean') throw new Error('Respons tidak valid');
                this.eligibility = decision;
            } catch (error) {
                this.eligibility = { allowed: false, exempt: false, status_label: 'Belum dapat diverifikasi', message: 'Presensi belum dapat diverifikasi. Jurnal belum dapat disimpan. Silahkan coba cek ulang presensi.' };
            } finally {
                this.checking = false;
                this.$dispatch('journal-attendance-checked', this.eligibility);
            }
        }
     }"
     :class="eligibility.allowed ? 'border-info-line bg-info-soft text-info-ink' : 'border-warning-line bg-warning-soft text-warning-ink'">
    <p class="font-medium" aria-live="polite" x-text="eligibility.message">{{ $eligibility['message'] }}</p>
    <button type="button" class="mt-3 rounded-lg border border-current px-3 py-2 font-medium disabled:opacity-50"
            @click="refresh()" :disabled="checking" x-text="checking ? 'Memeriksa...' : 'Cek ulang presensi'">Cek ulang presensi</button>
</div>
