<style id="gq-theme-palette">
    {!! app(\App\Services\ThemePaletteService::class)->cssVariables(($useDefaultThemePalette ?? false) ? app(\App\Services\ThemePaletteService::class)->defaults() : null) !!}
</style>
