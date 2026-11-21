(() => {
    const form = document.querySelector('[data-dispensing-history-form]');
    const input = document.querySelector('[data-dispensing-history-search]');
    const dateFrom = document.querySelector('[data-history-date-from]');
    const dateTo = document.querySelector('[data-history-date-to]');
    const pageSize = document.querySelector('#history-per-page');
    const groupsContainer = document.querySelector('[data-history-groups]');
    const count = document.querySelector('[data-history-count]');
    const pagination = document.querySelector('[data-history-pagination]');
    if (!form || !input || !groupsContainer || !count || !pagination) return;

    let timer = 0;
    let requestId = 0;
    const minimumMessage = input.dataset.minimumMessage || '';
    const emptyMessage = groupsContainer.dataset.emptyMessage || '';
    const errorMessage = groupsContainer.dataset.errorMessage || '';
    const language = (form.dataset.language || 'nl').toLowerCase();

    function setCount(start, end, total) {
        count.textContent = (count.dataset.countTemplate || '')
            .replace('{start}', String(start))
            .replace('{end}', String(end))
            .replace('{total}', String(total));
    }

    function showTableMessage(message) {
        groupsContainer.replaceChildren();
        const messageElement = document.createElement('div');
        messageElement.className = 'text-center text-secondary py-4';
        messageElement.textContent = message;
        groupsContainer.append(messageElement);
    }

    function renderRows(rows) {
        groupsContainer.replaceChildren();
        if (!rows.length) {
            showTableMessage(emptyMessage);
            return;
        }
        const sessions = new Map();
        for (const item of rows) {
            const key = String(item.history_id || item.session_id || '');
            if (!sessions.has(key)) sessions.set(key, []);
            sessions.get(key).push(item);
        }
        const label = (name) => groupsContainer.dataset[`${name}Label`] || '';
        const addHeaderItem = (header, name, value, title = '') => {
            const item = document.createElement('span');
            const prefix = document.createElement('span');
            prefix.className = 'text-secondary';
            prefix.textContent = `${label(name)}: `;
            const content = document.createElement(title ? 'code' : 'span');
            content.textContent = String(value ?? '—');
            if (title) content.title = title;
            item.append(prefix, content);
            header.append(item);
        };
        for (const sessionRows of sessions.values()) {
            const first = sessionRows[0];
            const card = document.createElement('section');
            card.className = 'card mb-3';
            const header = document.createElement('div');
            header.className = 'card-header d-flex align-items-center gap-3 flex-wrap';
            const date = document.createElement('strong');
            date.className = 'text-nowrap';
            date.textContent = String(first.completed_at_display || '');
            header.append(date);
            addHeaderItem(header, 'session', `${String(first.session_id || '').slice(0, 8)}…`, String(first.session_id || ''));
            addHeaderItem(header, 'prescription', first.prescription_number == null ? '—' : `#${first.prescription_number}`);
            card.append(header);

            const responsive = document.createElement('div');
            responsive.className = 'table-responsive';
            const table = document.createElement('table');
            table.className = 'table table-sm table-striped table-hover align-middle mb-0';
            const thead = table.createTHead();
            const headerRow = thead.insertRow();
            ['recipient', 'status', 'cnk', 'product', 'quantity'].forEach((name) => {
                const cell = document.createElement('th');
                cell.textContent = label(name);
                if (name === 'quantity') cell.className = 'text-end';
                headerRow.append(cell);
            });
            const tbody = table.createTBody();
            for (const item of sessionRows) {
                const row = tbody.insertRow();
                const recipient = row.insertCell();
                const partyName = document.createElement('span');
                partyName.textContent = String(item.party_name || '');
                if (item.customer_code) {
                    partyName.title = String(item.customer_code);
                }
                recipient.append(partyName);
                const status = document.createElement('span');
                status.className = 'badge text-bg-light';
                status.textContent = String(item.status_code || '');
                row.insertCell().append(status);
                const cnkText = document.createElement('small');
                cnkText.textContent = String(item.cnk || '');
                row.insertCell().append(cnkText);
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
            responsive.append(table);
            card.append(responsive);
            groupsContainer.append(card);
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
