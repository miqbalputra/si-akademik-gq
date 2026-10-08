@include('partials.theme-palette')
<script>
    (() => {
        const root = document.documentElement;
        const key = 'ruang-gq-theme-{{ auth('admin')->id() ? (int) auth('admin')->id() : 'guest' }}';
        const paletteStyle = document.getElementById('gq-theme-palette');

        // Filament loads its own Chart.js instance; use the shared font there too.
        window.filamentChartJsGlobalPlugins = [
            ...(window.filamentChartJsGlobalPlugins ?? []),
            {
                id: 'gq-outfit-typography',
                beforeInit(chart) {
                    chart.options.font = { ...chart.options.font, family: 'Outfit, sans-serif', size: 12, lineHeight: 1.5 };
                    document.fonts?.ready.then(() => {
                        if (chart.ctx) chart.update('none');
                    });
                },
            },
        ];

        window.addEventListener('gq-theme-palette-saved', (event) => {
            if (paletteStyle && typeof event.detail?.css === 'string' && event.detail.css.startsWith(':root{')) {
                paletteStyle.textContent = event.detail.css;
            }
        });

        try {
            const saved = localStorage.getItem(key) ?? 'light';
            localStorage.setItem('theme', saved);
            root.classList.toggle('dark', saved === 'dark');

            new MutationObserver(() => {
                localStorage.setItem(key, root.classList.contains('dark') ? 'dark' : 'light');
            }).observe(root, { attributes: true, attributeFilter: ['class'] });
        } catch (_) {}
    })();
</script>
