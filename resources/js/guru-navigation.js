export function guruMenuMatches(label, group, query) {
    const normalize = value => value.normalize('NFKD').toLocaleLowerCase('id').replace(/\p{Diacritic}/gu, '').replace(/\s+/g, ' ').trim();
    return normalize(`${group} ${label}`).includes(normalize(query));
}

export function groupIsOpen(group, saved) {
    return group.active || (typeof saved?.[group.key] === 'boolean' ? saved[group.key] : group.defaultOpen);
}

export function initGuruNavigation() {
    const nav = document.querySelector('[data-guru-nav]');
    if (!nav || nav.dataset.initialized) return;
    nav.dataset.initialized = 'true';
    const input = nav.querySelector('[data-guru-menu-search]');
    const clear = nav.querySelector('[data-guru-menu-clear]');
    const status = nav.querySelector('[data-guru-menu-status]');
    const empty = nav.querySelector('[data-guru-menu-empty]');
    const home = nav.querySelector('[data-guru-home]');
    const groups = [...nav.querySelectorAll('[data-guru-group]')];
    let saved = {};
    try {
        const parsed = JSON.parse(localStorage.getItem(nav.dataset.storageKey));
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) saved = parsed;
    } catch (_) { /* Navigation works when storage is unavailable. */ }
    const restore = () => groups.forEach(group => {
        group.open = groupIsOpen({
            key: group.dataset.guruGroup,
            active: group.dataset.active === 'true',
            defaultOpen: group.dataset.defaultOpen === 'true',
        }, saved);
    });
    restore();
    groups.forEach(group => group.addEventListener('toggle', () => {
        if (input.value.trim()) return;
        if (group.dataset.active === 'true' && !group.open) {
            group.open = true;
            return;
        }
        saved[group.dataset.guruGroup] = group.open;
        try { localStorage.setItem(nav.dataset.storageKey, JSON.stringify(saved)); } catch (_) {}
    }));

    const filter = () => {
        const query = input.value.trim();
        let count = 0;
        for (const group of groups) {
            let visible = 0;
            for (const item of group.querySelectorAll('[data-guru-item]')) {
                item.hidden = !guruMenuMatches(item.dataset.searchLabel, group.querySelector('summary').textContent, query);
                if (!item.hidden) visible++;
            }
            group.hidden = visible === 0;
            if (query && visible) group.open = true;
            count += visible;
        }
        home.hidden = !guruMenuMatches('Beranda', '', query);
        if (!home.hidden) count++;
        if (!query) restore();
        clear.hidden = !query;
        status.textContent = query ? `${count} menu ditemukan` : '';
        empty.hidden = !query || count > 0;
    };
    const reset = () => { input.value = ''; filter(); input.focus(); };
    input.addEventListener('input', filter);
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape' && input.value) {
            event.preventDefault();
            event.stopPropagation();
            reset();
        }
    });
    clear.addEventListener('click', reset);
}

if (typeof document !== 'undefined') {
    document.addEventListener('livewire:navigated', initGuruNavigation);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGuruNavigation);
    else initGuruNavigation();
}
