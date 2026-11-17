export function menuMatches(label, group, query) {
    const normalize = value => value.normalize('NFKD').toLocaleLowerCase('id').replace(/\p{Diacritic}/gu, '').replace(/\s+/g, ' ').trim();
    return normalize(`${group} ${label}`).includes(normalize(query));
}

export function initAdminNavigation() {
    const root = document.querySelector('[data-gq-menu-search-root]');
    const sidebar = window.Alpine?.store('sidebar');
    if (!root || !sidebar || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const nav = root.closest('nav');
    const list = nav.querySelector('ul');
    if (list) list.id = 'gq-admin-menu-list';
    const input = root.querySelector('[data-gq-menu-search]');
    const groups = [...nav.querySelectorAll('[data-gq-nav-group]')];
    const labels = groups.map(group => group.dataset.gqNavGroup);
    const activeLabels = groups.filter(group => group.classList.contains('fi-active')).map(group => group.dataset.gqNavGroup);
    const restoreActive = collapsed => collapsed.filter(label => labels.includes(label) && !activeLabels.includes(label));
    let collapsed = groups.filter(group => group.dataset.gqNavDefaultCollapsed === 'true').map(group => group.dataset.gqNavGroup);
    try {
        const saved = JSON.parse(localStorage.getItem(root.dataset.storageKey));
        if (Array.isArray(saved)) collapsed = saved;
    } catch (_) {}
    sidebar.collapsedGroups = restoreActive(collapsed);
    let beforeSearch = null;

    const filter = () => {
        const query = input.value.trim();
        if (query && beforeSearch === null) beforeSearch = [...sidebar.collapsedGroups];
        let count = 0;
        for (const group of groups) {
            let visible = 0;
            for (const item of group.querySelectorAll('[data-gq-nav-key]')) {
                const match = menuMatches(item.querySelector('.fi-sidebar-item-label')?.textContent ?? '', group.dataset.gqNavGroup, query);
                item.classList.toggle('gq-menu-hidden', !match);
                if (match) visible++;
            }
            count += visible;
            group.classList.toggle('gq-menu-hidden', visible === 0);
        }
        // The ungrouped dashboard is filtered too, and has no accordion state.
        for (const item of nav.querySelectorAll('[data-gq-nav-key="dashboard"]')) {
            const match = menuMatches(item.textContent, '', query);
            item.classList.toggle('gq-menu-hidden', !match);
            if (match) count++;
        }
        if (query) sidebar.collapsedGroups = [];
        else if (beforeSearch !== null) {
            sidebar.collapsedGroups = restoreActive(beforeSearch);
            beforeSearch = null;
        }
        root.querySelector('[data-gq-menu-search-status]').textContent = query ? `${count} menu ditemukan` : '';
        root.querySelector('[data-gq-menu-empty]').hidden = !query || count > 0;
    };
    input.addEventListener('input', filter);
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape' && input.value) {
            event.preventDefault();
            event.stopPropagation();
            input.value = '';
            filter();
        }
    });
    root.querySelector('[data-gq-menu-clear]').addEventListener('click', () => {
        input.value = '';
        filter();
        input.focus();
    });
    nav.addEventListener('click', event => {
        if (!event.target.closest('.fi-sidebar-group-btn') || input.value.trim()) return;
        requestAnimationFrame(() => {
            try { localStorage.setItem(root.dataset.storageKey, JSON.stringify(sidebar.collapsedGroups)); } catch (_) {}
        });
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('alpine:initialized', initAdminNavigation);
    document.addEventListener('livewire:navigated', initAdminNavigation);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAdminNavigation);
    else initAdminNavigation();
}
