(() => {
    const form = document.querySelector('[data-dispensing-history-form]');
    const input = document.querySelector('[data-dispensing-history-search]');
    const dateFrom = document.querySelector('[data-history-date-from]');
    const dateTo = document.querySelector('[data-history-date-to]');
    const pageSize = document.querySelector('#history-per-page');
    const body = document.querySelector('[data-history-rows]');
    const count = document.querySelector('[data-history-count]');
    const pagination = document.querySelector('[data-history-pagination]');
    if (!form || !input || !body || !count || !pagination) return;

    let timer = 0;
    let requestId = 0;
    const minimumMessage = input.dataset.minimumMessage || '';
    const emptyMessage = body.dataset.emptyMessage || '';
    const errorMessage = body.dataset.errorMessage || '';
    const language = (form.dataset.language || 'nl').toLowerCase();

    function setCount(start, end, total) {
        count.textContent = (count.dataset.countTemplate || '')
            .replace('{start}', String(start))
            .replace('{end}', String(end))
            .replace('{total}', String(total));
    }

    function showTableMessage(message) {
        body.replaceChildren();
        const row = body.insertRow();
        const cell = row.insertCell();
        cell.colSpan = 8;
        cell.className = 'text-center text-secondary py-4';
        cell.textContent = message;
    }

    function renderRows(rows) {
        body.replaceChildren();
        if (!rows.length) {
            showTableMessage(emptyMessage);
            return;
        }
        for (const item of rows) {
            const row = body.insertRow();
            const sessionCell = row.insertCell();
            sessionCell.className = 'text-nowrap';
            sessionCell.textContent = String(item.completed_at_display || '');

            const sessionReference = row.insertCell();
            const code = document.createElement('code');
            code.title = String(item.session_id || '');
            code.textContent = `${String(item.session_id || '').slice(0, 8)}…`;
            sessionReference.append(code);

            row.insertCell().textContent = item.prescription_number == null ? '—' : `#${item.prescription_number}`;
            const recipient = row.insertCell();
            recipient.textContent = String(item.party_name || '');
            if (item.customer_code) {
                const codeLabel = document.createElement('span');
                codeLabel.className = 'd-block small text-secondary';
                codeLabel.textContent = String(item.customer_code);
                recipient.append(codeLabel);
            }
            const statusCell = row.insertCell();
            const status = document.createElement('span');
            status.className = 'badge text-bg-light';
            status.textContent = String(item.status_code || '');
            statusCell.append(status);
            const cnk = row.insertCell();
            const cnkText = document.createElement('small');
            cnkText.textContent = String(item.cnk || '');
            cnk.append(cnkText);
            const product = row.insertCell();
            const name = document.createElement('strong');
            name.textContent = String((language === 'fr' ? item.product_name_fr : item.product_name_nl)
                || item.product_name_nl || item.product_name_fr || '');
            product.append(name);
            const packageText = String((language === 'fr' ? item.package_display_fr : item.package_display_nl)
                || item.package_display_nl || item.package_display_fr || '');
            if (packageText) {
                const pack = document.createElement('span');
                pack.className = 'ms-2 fw-bold';
                pack.textContent = packageText;
                product.append(pack);
            }
            const quantity = row.insertCell();
            quantity.className = 'text-end';
            quantity.textContent = String(item.quantity ?? '');
        }
    }

    function renderPagination(data) {
        pagination.replaceChildren();
        const list = document.createElement('ul');
        list.className = 'pagination pagination-sm mb-0';
        const actions = [
            [1, pagination.dataset.first, data.page === 1],
            [Math.max(1, data.page - 1), pagination.dataset.previous, data.page === 1],
            [Math.min(data.pages, data.page + 1), pagination.dataset.next, data.page >= data.pages],
            [data.pages, pagination.dataset.last, data.page >= data.pages],
        ];
        actions.forEach(([target, label, disabled]) => {
            const item = document.createElement('li');
            item.className = `page-item${disabled ? ' disabled' : ''}`;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'page-link';
            button.textContent = String(label || '');
            button.disabled = Boolean(disabled);
            button.addEventListener('click', () => { void search(Number(target)); });
            item.append(button);
            list.append(item);
        });
        pagination.append(list);
    }

    async function search(page = 1) {
        const query = input.value.trim();
        if (query.length > 0 && query.length < 3) {
            requestId += 1;
            showTableMessage(minimumMessage);
            setCount(0, 0, 0);
            pagination.replaceChildren();
            return;
        }
        if (dateFrom?.value && dateTo?.value && dateFrom.value > dateTo.value) {
            requestId += 1;
            showTableMessage(count.dataset.dateRangeError || '');
            setCount(0, 0, 0);
            pagination.replaceChildren();
            dateTo.focus();
            return;
        }
        const currentRequest = ++requestId;
        showTableMessage('…');
        const params = new URLSearchParams({
            history_api: '1', scope: 'all', q: query,
            page: String(page), per_page: String(pageSize?.value || 25),
        });
        if (dateFrom?.value) params.set('date_from', dateFrom.value);
        if (dateTo?.value) params.set('date_to', dateTo.value);
        try {
            const response = await fetch(`${form.action}?${params}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(errorMessage);
            const data = await response.json();
            if (currentRequest !== requestId) return;
            renderRows(data.rows || []);
            setCount(data.total ? ((data.page - 1) * data.per_page) + 1 : 0, Math.min(data.page * data.per_page, data.total), data.total || 0);
            renderPagination(data);
            const url = new URL(form.action, window.location.href);
            if (query) url.searchParams.set('q', query);
            else url.searchParams.delete('q');
            url.searchParams.set('per_page', String(data.per_page || 25));
            url.searchParams.set('page', String(data.page || 1));
            if (dateFrom?.value) url.searchParams.set('date_from', dateFrom.value);
            else url.searchParams.delete('date_from');
            if (dateTo?.value) url.searchParams.set('date_to', dateTo.value);
            else url.searchParams.delete('date_to');
            window.history.replaceState(null, '', url);
        } catch {
            if (currentRequest === requestId) {
                showTableMessage(errorMessage);
                setCount(0, 0, 0);
                pagination.replaceChildren();
            }
        }
    }

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        const query = input.value.trim();
        if (query.length > 0 && query.length < 3) {
            search(1);
            return;
        }
        timer = window.setTimeout(() => { void search(1); }, 250);
    });
    dateFrom?.addEventListener('change', () => { void search(1); });
    dateTo?.addEventListener('change', () => { void search(1); });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(timer);
        void search(1);
    });
    pageSize?.addEventListener('change', () => { void search(1); });
})();
