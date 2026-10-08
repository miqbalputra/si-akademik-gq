<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-clipboard-document-check" heading="Manajemen Presensi Kelas" description="Pusat kelola data kehadiran santri">
        <div style="display:flex;flex-direction:column;gap:1rem;">
            <p style="font-size:14px;color:var(--ui-text);line-height:20px;font-weight:400;" class="dark:text-body">
                Buka halaman presensi bulanan untuk menginput kehadiran (Hadir, Sakit, Izin, Alpha, dan Libur) per santri secara langsung.
            </p>
            
            <p style="font-size:14px;color:var(--ui-text);line-height:20px;font-weight:400;" class="dark:text-body">
                Anda juga bisa melakukan <strong>Import file XLSX</strong> rekap absensi lama menggunakan tombol di kanan atas halaman ini. Pastikan format spreadsheet yang diimpor memiliki format sheet nama bulan seperti <em>JUL-25, AGU-25</em>, dan seterusnya.
            </p>

            <div style="margin-top:0.5rem;">
                <x-filament::button 
                    tag="a" 
                    href="{{ route('attendance.index') }}" 
                    icon="heroicon-m-arrow-top-right-on-square"
                    size="lg"
                >
                    Buka Portal Presensi
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
