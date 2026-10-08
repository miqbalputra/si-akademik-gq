<script>
    (() => {
        const root = document.documentElement;
        const key = 'ruang-gq-theme-{{ auth()->id() ? (int) auth()->id() : 'guest' }}';
        root.dataset.themeStorageKey = key;

        try {
            const saved = localStorage.getItem(key) ?? 'light';
            root.classList.toggle('dark', saved === 'dark');
        } catch (_) {}
    })();
</script>
