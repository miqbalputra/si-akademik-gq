<details class="mt-3 text-theme-sm">
    <summary class="cursor-pointer font-medium underline">Lihat pengajar, kelas, dan jadwal ({{ count($teachers) }})</summary>
    <div class="mt-3 space-y-3">
        @forelse($teachers as $teacher)
            <div class="rounded-xl border border-line p-3">
                <p class="font-medium text-heading">{{ $teacher['name'] }} · {{ ['male' => 'Laki-laki', 'female' => 'Perempuan'][$teacher['gender']] ?? 'Gender belum diisi' }}</p>
                @foreach($teacher['classes'] as $class)
                    <div class="mt-2 text-muted">
                        <p>{{ $class['name'] }} · {{ $class['role'] }}</p>
                        @if($class['starts_at'] || $class['ends_at'])<p>Tugas: {{ $class['starts_at'] ?? 'awal semester' }} s.d. {{ $class['ends_at'] ?? 'akhir semester' }}</p>@endif
                        @forelse($class['schedules'] as $schedule)<p>{{ $schedule }}</p>@empty<p>Belum dijadwalkan</p>@endforelse
                    </div>
                @endforeach
            </div>
        @empty
            <p class="text-muted">Belum ada guru aktif dengan akun guru dan penugasan yang sesuai pada semester ini.</p>
        @endforelse
    </div>
</details>
