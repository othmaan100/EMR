/**
 * Type-ahead patient picker.
 *
 * <div data-patient-picker data-url="/patients/lookup">
 *   <input type="hidden" name="patient_id">
 *   <input type="search" data-picker-input class="form-control">
 *   <div data-picker-results class="list-group"></div>
 *   <div data-picker-selected></div>
 * </div>
 */
export function initPatientPickers() {
    document.querySelectorAll('[data-patient-picker]').forEach((root) => {
        const hidden = root.querySelector('input[type=hidden]');
        const input = root.querySelector('[data-picker-input]');
        const results = root.querySelector('[data-picker-results]');
        const selected = root.querySelector('[data-picker-selected]');
        let timer = null;
        let controller = null;

        const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

        const showSelected = (label) => {
            selected.innerHTML = `<div class="d-flex justify-content-between align-items-center border rounded p-2 bg-light">
                <span>${label}</span>
                <button type="button" class="btn btn-sm btn-link" data-picker-clear>Change</button></div>`;
            selected.classList.remove('d-none');
            input.classList.add('d-none');
            results.innerHTML = '';
        };

        selected.addEventListener('click', (e) => {
            if (!e.target.closest('[data-picker-clear]')) return;
            hidden.value = '';
            selected.classList.add('d-none');
            input.classList.remove('d-none');
            input.value = '';
            input.focus();
        });

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) {
                results.innerHTML = '';
                return;
            }
            timer = setTimeout(async () => {
                controller?.abort();
                controller = new AbortController();
                try {
                    const res = await fetch(`${root.dataset.url}?q=${encodeURIComponent(q)}`, {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    });
                    const rows = await res.json();
                    results.innerHTML = rows.length
                        ? rows.map((p) => `<button type="button" class="list-group-item list-group-item-action"
                                data-id="${p.id}" data-label="${escape(p.hospital_number)} — ${escape(p.name)}">
                                <strong>${escape(p.hospital_number)}</strong> ${escape(p.name)}
                                <small class="d-block text-muted">${escape(p.meta)}</small></button>`).join('')
                        : '<div class="list-group-item text-muted small">No matching patient</div>';
                } catch (err) {
                    if (err.name !== 'AbortError') results.innerHTML = '<div class="list-group-item text-danger small">Search failed</div>';
                }
            }, 250);
        });

        results.addEventListener('click', (e) => {
            const item = e.target.closest('[data-id]');
            if (!item) return;
            hidden.value = item.dataset.id;
            showSelected(item.dataset.label);
        });

        if (hidden.value && root.dataset.selectedLabel) {
            showSelected(escape(root.dataset.selectedLabel));
        }
    });
}
