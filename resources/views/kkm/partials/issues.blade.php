@if($roster['issues'])
    <details class="rounded-xl border border-line bg-surface p-4">
        <summary class="cursor-pointer font-medium">Data jenjang perlu dilengkapi ({{ count($roster['issues']) }})</summary>
        <p class="mt-2 text-theme-sm text-muted">Isi tingkat kelas dengan M1–M6 atau Mustawa 1–6 agar dapat dikelompokkan untuk KKM.</p>
        <ul class="mt-2 list-inside list-disc text-theme-sm">
            @foreach($roster['issues'] as $issue)<li>{{ $issue['class'] }} · {{ $issue['subject'] }} · Tingkat: {{ $issue['level'] ?: 'Belum diisi' }}</li>@endforeach
        </ul>
    </details>
@endif
