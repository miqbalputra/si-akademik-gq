<x-layouts.portal :title="$title" portalLabel="Portal Wali Santri" breadcrumb="Kalender">
    <div class="space-y-6">
        <header class="school-dashboard-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber text-theme-xs font-medium">Portal Wali Santri</span>
                <h1 class="mt-3 text-on-primary ui-page-title">{{ $title }}</h1>
                <p class="mt-2 max-w-2xl text-theme-sm font-normal text-on-primary/80">{{ $subtitle }}</p>
            </div>
        </header>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="calendar-filter-heading">
            <div class="mb-4">
                <p class="text-theme-xs font-normal uppercase text-warning-ink">Atur tampilan</p>
                <h2 id="calendar-filter-heading" class="mt-1 text-heading ui-form-title">Filter kalender akademik</h2>
            </div>
            <form method="GET" class="grid items-end gap-4 sm:grid-cols-3">
                <div>
                    <label for="calendar-term" class="mb-1.5 block text-body ui-form-label">Periode Akademik</label>
                    <select id="calendar-term" name="term" class="form-input min-h-11 text-theme-sm font-normal" aria-label="Periode akademik">
                        @foreach ($termOptions as $term)
                            <option value="{{ $term['id'] }}" @selected($selectedAcademicTermId === $term['id'])>{{ $term['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="calendar-month" class="mb-1.5 block text-body ui-form-label">Bulan</label>
                    <input id="calendar-month" type="month" name="month" value="{{ $selectedMonth }}" class="form-input min-h-11 text-theme-sm font-normal" aria-label="Bulan kalender">
                </div>
                <div>
                    <label for="calendar-category" class="mb-1.5 block text-body ui-form-label">Kategori</label>
                    <select id="calendar-category" name="category" class="form-input min-h-11 text-theme-sm font-normal" aria-label="Kategori kalender">
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category['value'] }}" @selected($selectedCategory === $category['value'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary min-h-11 w-full sm:col-span-3 text-theme-sm font-medium">Tampilkan Kalender</button>
            </form>
            <div class="mt-4 flex flex-wrap gap-x-2 gap-y-1 border-t border-line pt-4 text-theme-xs font-normal text-muted" aria-live="polite">
                <span>{{ $selectedTermLabel }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $selectedMonthLabel }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>Filter {{ collect($categoryOptions)->firstWhere('value', $selectedCategory)['label'] ?? 'Semua' }}</span>
            </div>
        </section>

        @include('calendar._calendar')
    </div>
</x-layouts.portal>
