<x-layouts.portal :title="$title" portalLabel="Portal Wali Santri" breadcrumb="Kalender">
    <div class="space-y-6">
        <header class="vantis-hero p-6 sm:p-8">
            <div class="relative z-10">
                <span class="badge badge-amber">Portal Wali Santri</span>
                <h1 class="mt-3 text-3xl font-black leading-tight text-white sm:text-4xl">{{ $title }}</h1>
                <p class="mt-2 max-w-2xl text-sm font-medium text-slate-300">{{ $subtitle }}</p>
            </div>
        </header>

        <section class="card-lg p-5 sm:p-6" aria-labelledby="calendar-filter-heading">
            <div class="mb-4">
                <p class="text-xs font-black uppercase tracking-[.14em] text-amber-700">Atur tampilan</p>
                <h2 id="calendar-filter-heading" class="mt-1 text-lg font-black text-slate-900">Filter kalender akademik</h2>
            </div>
            <form method="GET" class="grid items-end gap-4 sm:grid-cols-3">
                <div>
                    <label for="calendar-term" class="mb-1.5 block text-xs font-bold text-slate-600">Periode Akademik</label>
                    <select id="calendar-term" name="term" class="form-input min-h-11" aria-label="Periode akademik">
                        @foreach ($termOptions as $term)
                            <option value="{{ $term['id'] }}" @selected($selectedAcademicTermId === $term['id'])>{{ $term['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="calendar-month" class="mb-1.5 block text-xs font-bold text-slate-600">Bulan</label>
                    <input id="calendar-month" type="month" name="month" value="{{ $selectedMonth }}" class="form-input min-h-11" aria-label="Bulan kalender">
                </div>
                <div>
                    <label for="calendar-category" class="mb-1.5 block text-xs font-bold text-slate-600">Kategori</label>
                    <select id="calendar-category" name="category" class="form-input min-h-11" aria-label="Kategori kalender">
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category['value'] }}" @selected($selectedCategory === $category['value'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary min-h-11 w-full sm:col-span-3">Tampilkan Kalender</button>
            </form>
            <div class="mt-4 flex flex-wrap gap-x-2 gap-y-1 border-t border-slate-100 pt-4 text-xs font-bold text-slate-500" aria-live="polite">
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
