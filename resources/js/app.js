import Chart from 'chart.js/auto';

window.Chart = Chart;

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function initLandingFactory() {
    const root = document.querySelector('[data-factory]');
    if (!root) return;

    const buttons = [...root.querySelectorAll('[data-factory-stage]')];
    const title = root.querySelector('[data-factory-title]');
    const copy = root.querySelector('[data-factory-copy]');
    const label = root.querySelector('[data-factory-label]');
    const cta = root.querySelector('[data-factory-cta]');
    const metrics = root.querySelectorAll('[data-factory-metric]');

    const selectStage = (button, moveFocus = false) => {
        buttons.forEach((candidate) => candidate.setAttribute('aria-pressed', String(candidate === button)));

        if (label) label.textContent = button.dataset.label ?? '';
        if (title) title.textContent = button.dataset.title ?? '';
        if (copy) copy.textContent = button.dataset.copy ?? '';
        if (cta) {
            cta.textContent = button.dataset.cta ?? 'Masuk ke portal';
            cta.href = button.dataset.href ?? '/login';
        }

        const values = (button.dataset.metrics ?? '').split('|');
        metrics.forEach((metric, index) => {
            const value = metric.querySelector('strong');
            if (value && values[index]) value.textContent = values[index];
        });

        if (moveFocus) button.focus();
    };

    buttons.forEach((button, index) => {
        button.addEventListener('click', () => selectStage(button));
        button.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            let next = index;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = buttons.length - 1;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') next = (index + 1) % buttons.length;
            if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
            selectStage(buttons[next], true);
        });
    });

    const selected = buttons.find((button) => button.getAttribute('aria-pressed') === 'true') ?? buttons[0];
    if (selected) selectStage(selected);
}

function initFlowExplorer() {
    const root = document.querySelector('[data-flow-explorer]');
    if (!root) return;

    const detail = root.querySelector('[data-flow-detail]');
    const buttons = [...root.querySelectorAll('[data-flow-step]')];
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((candidate) => candidate.setAttribute('aria-pressed', String(candidate === button)));
            if (detail) detail.textContent = button.dataset.detail ?? '';
        });
    });
}

function initScrollSpy() {
    const links = [...document.querySelectorAll('[data-scroll-link]')];
    if (!links.length || !('IntersectionObserver' in window)) return;

    const sections = links
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!visible) return;

        links.forEach((link) => {
            const current = link.getAttribute('href') === `#${visible.target.id}`;
            link.toggleAttribute('aria-current', current);
        });
    }, { rootMargin: '-24% 0px -62% 0px', threshold: [0.1, 0.35, 0.65] });

    sections.forEach((section) => observer.observe(section));
}

function initPortalMenu() {
    document.querySelectorAll('[data-portal-menu]').forEach((menu) => {
        const summary = menu.querySelector('summary');
        menu.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !menu.open) return;
            event.preventDefault();
            menu.open = false;
            summary?.focus();
        });

        document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });
    });
}

function createNotificationElement(notification, readUrlTemplate, csrf) {
    const item = document.createElement('a');
    item.className = 'notification-item';
    item.href = notification.link_url || '#';
    item.dataset.notificationId = notification.id;

    const severity = {
        success: ['✓', 'bg-emerald-100 text-emerald-800'],
        warning: ['!', 'bg-amber-100 text-amber-800'],
        danger: ['!', 'bg-red-100 text-red-800'],
        info: ['i', 'bg-blue-100 text-blue-800'],
    }[notification.severity] ?? ['i', 'bg-slate-100 text-slate-700'];

    const icon = document.createElement('span');
    icon.className = `flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-black ${severity[1]}`;
    icon.textContent = severity[0];

    const content = document.createElement('span');
    content.className = 'min-w-0 flex-1';
    const heading = document.createElement('span');
    heading.className = 'block text-xs font-extrabold text-ink';
    heading.textContent = `${notification.title ?? 'Notifikasi'}${notification.batch_count > 1 ? ` ×${notification.batch_count}` : ''}`;
    const body = document.createElement('span');
    body.className = 'mt-1 block line-clamp-2 text-[11px] leading-4 text-slate-500';
    body.textContent = notification.body ?? '';
    const timestamp = document.createElement('span');
    timestamp.className = 'mt-1 block font-mono text-[10px] text-slate-400';
    timestamp.textContent = notification.created_at ?? '';
    content.append(heading, body, timestamp);
    item.append(icon, content);

    if (notification.status === 'unread') {
        const unread = document.createElement('span');
        unread.className = 'mt-1 h-2 w-2 shrink-0 rounded-full bg-neon';
        unread.setAttribute('aria-label', 'Belum dibaca');
        item.append(unread);
    }

    item.addEventListener('click', () => {
        if (!notification.id) return;
        fetch(readUrlTemplate.replace('__ID__', notification.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        }).catch(() => {});
    });

    return item;
}

function initNotifications() {
    document.querySelectorAll('[data-notification-root]').forEach((root) => {
        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        const list = root.querySelector('[data-notification-list]');
        const markAll = root.querySelector('[data-notification-mark-all]');
        const badges = [...document.querySelectorAll('[data-notification-badge]')];
        const feedUrl = root.dataset.feedUrl;
        const readUrlTemplate = root.dataset.readUrlTemplate;
        const markAllUrl = root.dataset.markAllUrl;
        const csrf = getCsrfToken();

        if (!toggle || !panel || !list || !feedUrl || !readUrlTemplate || !markAllUrl) return;

        const updateBadges = (count) => {
            badges.forEach((badge) => {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('hidden', count < 1);
            });
        };

        const render = (notifications) => {
            list.replaceChildren();
            if (!notifications.length) {
                const empty = document.createElement('p');
                empty.className = 'px-5 py-8 text-center text-xs font-bold text-slate-400';
                empty.textContent = 'Tidak ada notifikasi baru.';
                list.append(empty);
                return;
            }
            notifications.forEach((notification) => list.append(createNotificationElement(notification, readUrlTemplate, csrf)));
        };

        const poll = async () => {
            try {
                const response = await fetch(feedUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const payload = await response.json();
                updateBadges(payload.unread_count ?? 0);
                render(payload.notifications ?? []);
            } catch (_) {
                // The notification control remains usable if a transient request fails.
            }
        };

        const close = () => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', () => {
            const isOpen = !panel.hidden;
            panel.hidden = isOpen;
            toggle.setAttribute('aria-expanded', String(!isOpen));
            if (!isOpen) poll();
        });
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) close();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) {
                close();
                toggle.focus();
            }
        });
        markAll?.addEventListener('click', async () => {
            try {
                await fetch(markAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                await poll();
            } catch (_) {}
        });

        poll();
        window.setInterval(poll, 30000);
    });
}

function init() {
    initLandingFactory();
    initFlowExplorer();
    initScrollSpy();
    initPortalMenu();
    initNotifications();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
    init();
}
