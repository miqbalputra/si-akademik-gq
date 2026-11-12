<details class="rounded-2xl border border-line bg-surface p-4 sm:p-5" @if($errors->has('youtube_url')) open @endif>
    <summary class="cursor-pointer font-medium text-heading">Video panduan KKM</summary>
    <p class="mt-2 text-theme-sm text-muted">Atur satu video untuk seluruh guru. Tombol Lihat Video muncul di samping Lihat Panduan pada halaman KKM, termasuk untuk guru yang tidak ditugaskan menyusun KKM.</p>
    <form method="POST" action="{{ route('admin.kkm.video.update') }}" class="mt-4 space-y-3">
        @csrf @method('PUT')
        @php($videoInput = old('youtube_url', $videoUrl))
        <label class="ui-field">
            <span>Link video YouTube</span>
            <input type="url" name="youtube_url" value="{{ is_string($videoInput) ? $videoInput : '' }}" class="form-input" maxlength="2048" placeholder="https://www.youtube.com/watch?v=..." aria-describedby="kkm-video-hint" @if($errors->has('youtube_url')) aria-invalid="true" @endif>
        </label>
        <p id="kkm-video-hint" class="text-theme-sm {{ $errors->has('youtube_url') ? 'text-danger-ink' : 'text-muted' }}" @if($errors->has('youtube_url')) role="alert" @endif>{{ $errors->first('youtube_url') ?: 'Gunakan link video YouTube atau youtu.be. Kosongkan lalu simpan untuk menyembunyikan tombol video.' }}</p>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan video</button>
            @if($videoUrl)<a class="btn" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">Buka video <span class="sr-only">(YouTube, tab baru)</span></a>@endif
        </div>
    </form>
</details>
