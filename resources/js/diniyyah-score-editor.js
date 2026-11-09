export const scoreValue = value => value === '' || value == null ? null : Number(value).toFixed(2);

// One serialized queue for autosave and the explicit Save button. A response never
// replaces text typed while that request was in flight.
export class ScoreSaveQueue {
    constructor(send, onSaved = () => {}) {
        this.send = send;
        this.onSaved = onSaved;
        this.entries = new Map();
        this.running = null;
    }

    track(key, value, original, valid, ids) {
        const entry = this.entries.get(key) ?? { original: scoreValue(original), ...ids };
        Object.assign(entry, { value: scoreValue(value), valid });
        this.entries.set(key, entry);
    }

    get dirty() { return [...this.entries.values()].some(e => !e.valid || e.value !== e.original); }

    flush() {
        if (!this.running) this.running = this.drain().finally(() => { this.running = null; });
        return this.running;
    }

    async drain() {
        while (true) {
            const changes = [...this.entries.values()].filter(e => e.valid && e.value !== e.original).slice(0, 100)
                .map(e => ({ enrollment_id: e.enrollment_id, component_id: e.component_id, score: e.value, original: e.original }));
            if (!changes.length) return;
            const result = await this.send(changes);
            for (const cell of result.saved) {
                const entry = this.entries.get(`${cell.enrollment_id}-${cell.component_id}`);
                if (entry) entry.original = scoreValue(cell.score);
            }
            this.onSaved(result);
        }
    }
}

export function initDiniyyahScoreEditor() {
    document.querySelectorAll('[data-score-editor]').forEach(root => {
        const form = root.querySelector('[data-score-form]');
        const rows = [...root.querySelectorAll('[data-student]')];
        const filter = root.querySelector('#student-filter');
        filter?.addEventListener('input', () => rows.forEach(row => { row.hidden = !row.dataset.student.includes(filter.value.trim().toLowerCase()); }));
        if (root.dataset.canEdit !== '1') return;

        const status = root.querySelector('[data-save-status]');
        const error = root.querySelector('[data-save-error]');
        const saveButton = root.querySelector('[data-save-button]');
        const submitForm = root.querySelector('[data-score-submit]');
        const submitButton = submitForm?.querySelector('button');
        const addButtons = [...root.querySelectorAll('[data-add-component]')];
        const inputs = new Map([...root.querySelectorAll('input[data-score]')].map(input => [`${input.dataset.enrollmentId}-${input.dataset.componentId}`, input]));
        let timer, adding = false, saving = false, blocked = false;
        let complete = submitButton ? !submitButton.disabled : false;
        const next = { ulangan: root.dataset.nextUlangan, tugas: root.dataset.nextTugas };

        function controls() {
            saveButton.disabled = saving || adding || blocked;
            if (submitButton) submitButton.disabled = !complete || queue.dirty || saving || adding || blocked;
            addButtons.forEach(button => { button.disabled = saving || adding || blocked; });
        }
        function showError(message) {
            error.textContent = message;
            error.hidden = false;
            status.textContent = 'Ada perubahan yang belum tersimpan.';
        }
        async function request(url, method, body) {
            const controller = new AbortController();
            const requestTimeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(url, {
                    method, credentials: 'same-origin', signal: controller.signal,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify(body),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    if ([401, 403, 409, 419].includes(response.status)) blocked = true;
                    let message = Object.values(data.errors ?? {}).flat()[0] ?? data.message;
                    if ([401, 419].includes(response.status)) message = 'Sesi berakhir. Salin isian yang belum tersimpan, lalu masuk kembali.';
                    throw new Error(message || 'Penyimpanan gagal. Periksa koneksi lalu klik Simpan Perubahan.');
                }
                return data;
            } catch (exception) {
                if (exception.name === 'AbortError') throw new Error('Koneksi lambat. Klik Simpan Perubahan untuk mencoba lagi; isian belum dibuang.');
                throw exception;
            } finally { clearTimeout(requestTimeout); }
        }
        function progress(data) {
            root.querySelector('[data-filled-cells]').textContent = `${data.filled_cells}/${data.total_cells}`;
            root.querySelector('[data-complete-students]').textContent = `${data.complete_students}/${data.total_students}`;
            root.querySelector('[data-completion-percentage]').textContent = `${data.percentage}%`;
            complete = data.total_students > 0 && data.complete_students === data.total_students;
            if (submitButton) {
                submitButton.classList.toggle('bg-success-600', complete);
                submitButton.classList.toggle('bg-line-strong', !complete);
            }
            const below = new Set(data.below_kkm_enrollment_ids ?? []);
            const kkm = data.kkm == null ? '' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(data.kkm);
            root.querySelector('[data-kkm-warning]').hidden = below.size === 0;
            root.querySelector('[data-kkm-save-warning]').hidden = below.size === 0;
            root.querySelector('[data-below-kkm-count]').textContent = below.size;
            root.querySelector('[data-warning-kkm]').textContent = kkm;
            rows.forEach(row => {
                const warning = below.has(Number(row.dataset.enrollmentId));
                row.querySelector('[data-row-kkm-warning]').hidden = !warning;
                row.querySelector('[data-row-kkm]').textContent = kkm;
                row.querySelector('[data-row-final-score]').classList.toggle('text-warning-ink', warning);
            });
        }
        const queue = new ScoreSaveQueue(changes => request(root.dataset.autosaveUrl, 'PUT', { changes }), data => {
            data.saved.forEach(cell => {
                const input = inputs.get(`${cell.enrollment_id}-${cell.component_id}`);
                if (input) input.dataset.saved = cell.score ?? '';
            });
            (data.rows ?? []).forEach(result => {
                const row = rows.find(row => Number(row.dataset.enrollmentId) === result.enrollment_id);
                if (!row) return;
                row.querySelector('[data-row-final-score]').textContent = result.final_score ?? '-';
                const badge = row.querySelector('[data-row-complete]');
                badge.textContent = result.complete ? 'Lengkap' : 'Belum';
                ['bg-success-soft', 'text-success-ink', 'border-success-line'].forEach(c => badge.classList.toggle(c, result.complete));
                ['bg-warning-soft', 'text-warning-ink', 'border-warning-line'].forEach(c => badge.classList.toggle(c, !result.complete));
            });
            progress(data.progress);
        });

        async function save(manual = false) {
            clearTimeout(timer);
            if (blocked) return false;
            if (manual && !form.reportValidity()) return false;
            saving = true;
            error.hidden = true;
            status.textContent = 'Menyimpan perubahan…';
            controls();
            try {
                await queue.flush();
                if (queue.dirty) {
                    showError('Periksa isian: nilai 0–100 dengan maksimal dua angka desimal.');
                    return false;
                }
                status.textContent = `Semua perubahan tersimpan · ${new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date())}`;
                return true;
            } catch (exception) {
                showError(exception.message === 'Failed to fetch' ? 'Koneksi terputus. Isian masih ada di halaman ini. Klik Simpan Perubahan untuk mencoba lagi.' : exception.message);
                return false;
            } finally {
                saving = false;
                controls();
            }
        }
        form.addEventListener('input', event => {
            const input = event.target;
            if (!input.matches('input[data-score]') || input.readOnly || input.disabled) return;
            const key = `${input.dataset.enrollmentId}-${input.dataset.componentId}`;
            queue.track(key, input.value, input.dataset.saved, input.checkValidity(), { enrollment_id: Number(input.dataset.enrollmentId), component_id: Number(input.dataset.componentId) });
            if (!blocked) { error.hidden = true; status.textContent = 'Perubahan belum tersimpan…'; }
            controls();
            clearTimeout(timer);
            timer = setTimeout(() => save(), 800);
        });
        form.addEventListener('submit', event => { event.preventDefault(); save(true); });
        submitForm?.addEventListener('submit', event => {
            if (queue.dirty || saving || adding || blocked) { event.preventDefault(); showError('Simpan seluruh perubahan sebelum mengajukan nilai.'); }
        });
        window.addEventListener('beforeunload', event => {
            if (queue.dirty || saving || adding) { event.preventDefault(); event.returnValue = ''; }
        });
        window.addEventListener('online', () => { if (queue.dirty && !blocked) save(); });

        addButtons.forEach(button => button.addEventListener('click', async () => {
            if (adding || saving || blocked || !await save(true)) return;
            adding = true;
            status.textContent = 'Menambah kolom…';
            controls();
            try {
                const type = button.dataset.addComponent;
                const data = await request(root.dataset.componentUrl, 'POST', { type, expected_code: next[type] });
                const component = data.component;
                const following = data.order.slice(data.order.indexOf(component.id) + 1);
                const headingRow = form.querySelector('thead tr');
                if (!headingRow.querySelector(`[data-component-id="${component.id}"]`)) {
                    const heading = document.createElement('th');
                    heading.dataset.componentId = component.id;
                    heading.className = 'px-6 py-4 text-theme-xs font-medium';
                    heading.textContent = `${component.name} (tambahan opsional)`;
                    const nextCell = row => following.map(id => row.querySelector(`[data-component-id="${id}"]`)).find(Boolean);
                    headingRow.insertBefore(heading, nextCell(headingRow) ?? headingRow.querySelector('[data-result-heading]'));
                    rows.forEach(row => {
                        const cell = document.createElement('td');
                        cell.className = 'px-6 py-3';
                        Object.assign(cell.dataset, { componentId: component.id, label: component.name });
                        const input = root.querySelector('[data-score-input-template]').content.firstElementChild.cloneNode(true);
                        Object.assign(input.dataset, { saved: '', enrollmentId: row.dataset.enrollmentId, componentId: component.id });
                        input.name = `scores[${row.dataset.enrollmentId}][${component.id}]`;
                        input.setAttribute('aria-label', `${row.dataset.studentName} — ${component.name}`);
                        cell.append(input);
                        row.insertBefore(cell, nextCell(row) ?? row.querySelector('[data-row-result]'));
                        inputs.set(`${row.dataset.enrollmentId}-${component.id}`, input);
                    });
                }
                Object.assign(next, data.next);
                progress(data.progress);
                status.textContent = `${component.name} ditambahkan. Nilai lama tetap tersimpan.`;
            } catch (exception) { showError(exception.message); }
            finally { adding = false; controls(); }
        }));
        // A failed traditional submission may have restored old() values. They
        // must remain dirty until this editor has confirmed them with the server.
        inputs.forEach(input => {
            if (!input.readOnly && !input.disabled && scoreValue(input.value) !== scoreValue(input.dataset.saved)) input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        controls();
    });
}
