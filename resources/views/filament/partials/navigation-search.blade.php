<div class="gq-menu-search" data-gq-menu-search-root data-storage-key="gq-navigation-v2-{{ auth('admin')->id() }}-{{ auth('admin')->user()?->getRoleNames()->sort()->implode('-') }}">
    <label for="gq-menu-search">Cari menu</label>
    <div class="gq-menu-search-controls">
        <input id="gq-menu-search" data-gq-menu-search type="search" placeholder="Ketik nama menu…" autocomplete="off" aria-controls="gq-admin-menu-list">
        <button type="button" data-gq-menu-clear aria-label="Hapus pencarian menu" title="Hapus pencarian">×</button>
    </div>
    <p data-gq-menu-search-status role="status" aria-live="polite"></p>
    <p data-gq-menu-empty hidden>Tidak ada menu yang cocok.</p>
</div>
@vite('resources/js/admin-navigation.js')
