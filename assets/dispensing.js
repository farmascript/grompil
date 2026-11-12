(() => {
    const page = document.querySelector('[data-dispense-page]');
    if (!page) return;

    const modal = document.getElementById('patientModal');
    if (modal && page.dataset.modalOpen === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    const input = document.querySelector('[data-product-search]');
    const suggestions = document.querySelector('[data-product-suggestions]');
    const rows = document.querySelector('[data-basket-rows]');
    const count = document.querySelector('[data-basket-count]');
    const summary = document.querySelector('[data-basket-summary]');
    if (!input || !suggestions || !rows) return;

    const t = {
        add: page.dataset.textAdd,
        noResults: page.dataset.textNoProducts,
        unavailable: page.dataset.textProductError,
        empty: page.dataset.textEmptyBasket,
        remove: page.dataset.textRemove,
        quantity: page.dataset.textQuantity,
        productCount: page.dataset.textProductCount,
    };
    const language = (page.dataset.lang || 'nl').toLowerCase();
    const basket = new Map();
    let timer = 0;
    let requestController = null;

    function hideSuggestions() {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
    }

    function renderBasket() {
        rows.replaceChildren();
        const products = Array.from(basket.values());
        count.textContent = String(products.length);
        summary.textContent = t.productCount.replace('{count}', String(products.length));
        if (!products.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 3;
            td.className = 'empty-basket';
            td.textContent = t.empty;
            tr.append(td);
            rows.append(tr);
            summary.textContent = t.empty;
            return;
        }
        for (const product of products) {
            const tr = document.createElement('tr');
            const name = document.createElement('td');
            const strong = document.createElement('strong');
            strong.textContent = product.name;
            const small = document.createElement('small');
            small.textContent = `${product.cnk} · ${product.package || ''}`;
            name.append(strong, small);
            const quantity = document.createElement('td');
            const quantityInput = document.createElement('input');
            quantityInput.type = 'number';
            quantityInput.min = '1';
            quantityInput.max = '99';
            quantityInput.value = String(product.quantity);
            quantityInput.className = 'form-control form-control-sm basket-qty';
            quantityInput.setAttribute('aria-label', t.quantity);
            quantityInput.addEventListener('change', () => {
                product.quantity = Math.min(99, Math.max(1, Number.parseInt(quantityInput.value, 10) || 1));
                renderBasket();
            });
            quantity.append(quantityInput);
            const removeCell = document.createElement('td');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'basket-remove';
            remove.textContent = '×';
            remove.title = t.remove;
            remove.addEventListener('click', () => {
                basket.delete(product.key);
                renderBasket();
            });
            removeCell.append(remove);
            tr.append(name, quantity, removeCell);
            rows.append(tr);
        }
    }

    function addProduct(product) {
        const key = `${product.amp_code}|${product.ampp_code}|${product.cnk}`;
        const current = basket.get(key);
        if (current) current.quantity += 1;
        else basket.set(key, {
            key,
            cnk: String(product.cnk || ''),
            amp_code: String(product.amp_code || ''),
            ampp_code: String(product.ampp_code || ''),
            name: String((language === 'fr' ? product.prescription_name_fr : product.prescription_name_nl)
                || product.prescription_name_nl || product.prescription_name_fr || product.official_name || ''),
            package: String((language === 'fr' ? product.pack_display_fr : product.pack_display_nl)
                || product.pack_display_nl || product.pack_display_fr || ''),
            quantity: 1,
        });
        renderBasket();
        input.value = '';
        input.focus();
        hideSuggestions();
    }

    function showResults(data) {
        suggestions.replaceChildren();
        for (const product of data.results || []) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action product-suggestion';
            const title = document.createElement('strong');
            const productName = (language === 'fr' ? product.prescription_name_fr : product.prescription_name_nl)
                || product.prescription_name_nl || product.prescription_name_fr || product.official_name || '';
            const packageText = (language === 'fr' ? product.pack_display_fr : product.pack_display_nl)
                || product.pack_display_nl || product.pack_display_fr || '';
            title.textContent = `${product.cnk} — ${productName}`;
            const detail = document.createElement('small');
            detail.textContent = `${packageText} · ${product.amp_status}/${product.ampp_status}`;
            const add = document.createElement('span');
            add.className = 'btn btn-sm btn-outline-success mt-2';
            add.textContent = t.add;
            button.append(title, detail, add);
            button.addEventListener('click', () => addProduct(product));
            suggestions.append(button);
        }
        if (!(data.results || []).length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-secondary small';
            empty.textContent = t.noResults;
            suggestions.append(empty);
        }
        suggestions.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        if (requestController) requestController.abort();
        const query = input.value.trim();
        if (query.length < Number(page.dataset.minChars || 3)) {
            hideSuggestions();
            return;
        }
        timer = window.setTimeout(async () => {
            requestController = new AbortController();
            try {
                const url = new URL(page.dataset.productEndpoint, window.location.href);
                url.searchParams.set('q', query);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: requestController.signal });
                if (!response.ok) throw new Error('search_failed');
                showResults(await response.json());
            } catch (error) {
                if (error.name === 'AbortError') return;
                suggestions.replaceChildren();
                const message = document.createElement('div');
                message.className = 'list-group-item text-danger small';
                message.textContent = t.unavailable;
                suggestions.append(message);
                suggestions.classList.remove('d-none');
            }
        }, 220);
    });
    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== input) hideSuggestions();
    });
})();
