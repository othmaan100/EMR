/**
 * ICD-10 type-ahead. Free text is allowed; picking a suggestion also fills the code.
 *
 * <div data-icd-lookup data-url="/icd10/search" class="position-relative">
 *   <input name="description" data-icd-input>
 *   <input type="hidden" name="icd10_code">
 *   <div data-icd-results class="list-group position-absolute"></div>
 * </div>
 */
export function initIcdLookups() {
    document.querySelectorAll('[data-icd-lookup]').forEach((root) => {
        const input = root.querySelector('[data-icd-input]');
        const code = root.querySelector('input[name=icd10_code]');
        const badge = root.querySelector('[data-icd-badge]');
        const results = root.querySelector('[data-icd-results]');
        let timer;

        const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
        const setCode = (value) => {
            code.value = value;
            if (badge) {
                badge.textContent = value || 'no code';
                badge.className = value ? 'input-group-text fw-semibold text-brand' : 'input-group-text text-muted';
            }
        };

        input.addEventListener('input', () => {
            setCode(''); // typed text no longer matches a picked code
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) {
                results.innerHTML = '';
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const res = await fetch(`${root.dataset.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                    const rows = await res.json();
                    results.innerHTML = rows
                        .map((r) => `<button type="button" class="list-group-item list-group-item-action py-1"
                            data-code="${escape(r.code)}" data-desc="${escape(r.description)}">
                            <strong class="me-2">${escape(r.code)}</strong>${escape(r.description)}</button>`)
                        .join('');
                } catch {
                    results.innerHTML = '';
                }
            }, 200);
        });

        results.addEventListener('click', (e) => {
            const item = e.target.closest('[data-code]');
            if (!item) return;
            input.value = item.dataset.desc;
            setCode(item.dataset.code);
            results.innerHTML = '';
        });

        document.addEventListener('click', (e) => {
            if (!root.contains(e.target)) results.innerHTML = '';
        });
    });

    // Text filter over a list: <input data-filter-list="#listId"> hides [data-filter-item] not matching.
    document.querySelectorAll('[data-filter-list]').forEach((input) => {
        const list = document.querySelector(input.dataset.filterList);
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            list.querySelectorAll('[data-filter-item]').forEach((el) => {
                el.classList.toggle('d-none', q !== '' && !el.textContent.toLowerCase().includes(q));
            });
            list.querySelectorAll('[data-filter-group]').forEach((group) => {
                group.classList.toggle('d-none', !group.querySelector('[data-filter-item]:not(.d-none)'));
            });
        });
    });
}
