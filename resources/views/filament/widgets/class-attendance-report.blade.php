<x-filament-widgets::widget>
    <x-filament::section heading="Laporan Presensi Kelas" description="Unduh rekap kehadiran santri dan rincian harian dalam Excel atau PDF." icon="heroicon-o-arrow-down-tray">
        <x-attendance-report-download :options="$reportOptions" route-prefix="admin.class-attendance-report" />
    </x-filament::section>
</x-filament-widgets::widget>
