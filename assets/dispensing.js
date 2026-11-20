(() => {
    const page = document.querySelector('[data-dispense-page]');
    if (!page) return;

    const modal = document.getElementById('patientModal');
    if (modal && page.dataset.modalOpen === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    const contactModal = document.getElementById('contact-manager-modal');
    if (contactModal && page.dataset.contactModalOpen === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(contactModal).show();
    }
    const input = document.querySelector('[data-product-search]');
    const suggestions = document.querySelector('[data-product-suggestions]');
    const rows = document.querySelector('[data-basket-rows]');
    const count = document.querySelector('[data-basket-count]');
    const summary = document.querySelector('[data-basket-summary]');
    const partySelector = document.querySelector('[data-dispense-parties]');

    const patientInput = document.querySelector('[data-patient-search-input]');
    const patientForm = document.querySelector('[data-patient-search-form]');
    const patientSuggestions = document.querySelector('[data-patient-suggestions]');
    const patientStatus = document.querySelector('[data-patient-search-status]');
    const patientRegex = document.querySelector('[data-patient-regex]');
    const dispenseStatusSelect = document.querySelector('[data-dispense-status-select]');
    const patientStatusInput = document.querySelector('[data-patient-status-input]');
    const currentDispenseStatus = document.querySelector('[data-current-dispense-status]');
    const patientRequiredMessage = document.querySelector('[data-dispense-patient-required]');
    const initialPageCustomerCode = page.dataset.patientCode || '';
    let patientTimer = 0;
    let patientRequestController = null;

    function hidePatientSuggestions() {
        if (!patientSuggestions || !patientInput) return;
        patientSuggestions.classList.add('d-none');
        patientSuggestions.replaceChildren();
        patientInput.setAttribute('aria-expanded', 'false');
    }

    function setPatientStatus(message) {
        if (patientStatus) patientStatus.textContent = message;
    }

    function showPatientSuggestions(results) {
        patientSuggestions.replaceChildren();
        if (!results.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item small text-secondary';
            empty.textContent = page.dataset.textPatientNoResults || '';
            patientSuggestions.append(empty);
        } else {
            for (const patient of results) {
                const url = new URL(page.dataset.patientEndpoint, window.location.href);
                url.searchParams.set('patient', String(patient.customer_code || ''));
                url.searchParams.set('mode', 'patient');
                url.searchParams.set('dispense_status', dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'prescription');
                const link = document.createElement('a');
                link.className = 'list-group-item list-group-item-action patient-suggestion';
                link.href = url.href;
                const title = document.createElement('strong');
                title.textContent = String(patient.patient_name || '');
                const code = document.createElement('span');
                code.className = 'badge text-bg-light ms-2';
                code.textContent = String(patient.customer_code || '');
                const address = document.createElement('span');
                address.className = 'd-block small text-secondary';
                address.textContent = String(patient.patient_address || '');
                link.append(title, code, address);
                patientSuggestions.append(link);
            }
        }
        patientSuggestions.classList.remove('d-none');
        patientInput.setAttribute('aria-expanded', 'true');
    }

    function schedulePatientSearch() {
        if (!patientInput || !patientSuggestions) return;
        window.clearTimeout(patientTimer);
        if (patientRequestController) patientRequestController.abort();
        const query = patientInput.value.trim();
        const minimum = Number(page.dataset.patientMinChars || 3);
        if (query.length < minimum) {
            hidePatientSuggestions();
            setPatientStatus(query.length ? (page.dataset.textPatientMinimum || '') : (page.dataset.textPatientPrompt || ''));
            return;
        }
        setPatientStatus('');
        patientTimer = window.setTimeout(async () => {
            patientRequestController = new AbortController();
            try {
                const url = new URL(page.dataset.patientEndpoint, window.location.href);
                url.searchParams.set('patient_suggest', '1');
                url.searchParams.set('q', query);
                if (patientRegex?.checked) url.searchParams.set('regex', '1');
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: patientRequestController.signal,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || page.dataset.textPatientUnavailable || '');
                showPatientSuggestions(data.results || []);
            } catch (error) {
                if (error.name === 'AbortError') return;
                showPatientSuggestions([]);
                if (patientSuggestions.firstElementChild) {
                    patientSuggestions.firstElementChild.className = 'list-group-item small text-danger';
                    patientSuggestions.firstElementChild.textContent = error.message || page.dataset.textPatientUnavailable || '';
                }
            }
        }, 220);
    }

    if (patientInput && patientForm && patientSuggestions) {
        patientInput.addEventListener('input', schedulePatientSearch);
        patientRegex?.addEventListener('change', schedulePatientSearch);
        patientForm.addEventListener('submit', (event) => {
            if (patientInput.value.trim().length < Number(page.dataset.patientMinChars || 3)) {
                event.preventDefault();
                setPatientStatus(page.dataset.textPatientMinimum || '');
            }
        });
        patientInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') hidePatientSuggestions();
        });
        document.addEventListener('click', (event) => {
            if (!patientSuggestions.contains(event.target) && event.target !== patientInput) {
                hidePatientSuggestions();
            }
        });
    }

    if (patientSuggestions) {
        patientSuggestions.addEventListener('click', async (event) => {
            const suggestion = event.target.closest('a.patient-suggestion');
            if (!suggestion) return;
            const customerCode = new URL(suggestion.href, window.location.href).searchParams.get('patient') || '';
            if (!customerCode) return;
            event.preventDefault();
            await sessionReady;
            try {
                const data = await sessionRequest('party_add', { customer_code: customerCode });
                dispensingParties = data.parties || dispensingParties;
                basket.clear();
                for (const line of data.lines || []) basket.set(String(line.id), line);
                activateParty(data.active_party_id);
                patientInput.value = '';
                hidePatientSuggestions();
                setPatientStatus('');
            } catch {
                showSessionError();
            }
        });
    }

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
    let dispensingSessionId = '';
    let dispensingParties = [];
    let activePartyId = 0;
    let timer = 0;
    let requestController = null;
    let activeSuggestionIndex = -1;

    const sessionStorageKey = `grompil-dispensing-${page.dataset.sessionCompany || '0'}-${page.dataset.sessionUser || 'user'}`;

    async function sessionRequest(action, values = {}) {
        const response = await fetch(page.dataset.sessionEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                csrf_token: page.dataset.csrfToken,
                session_id: dispensingSessionId,
                action,
                ...values,
            }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || page.dataset.textSessionError || '');
        return data;
    }

    function showSessionError() {
        const panel = document.querySelector('.current-dispense-panel');
        if (!panel) return;
        let notice = panel.querySelector('.dispense-session-notice');
        if (!notice) {
            notice = document.createElement('div');
            notice.className = 'alert alert-danger small dispense-session-notice';
            panel.prepend(notice);
        }
        notice.textContent = page.dataset.textSessionError || '';
    }

    function renderPartySelector() {
        if (!partySelector) return;
        partySelector.replaceChildren();
        const label = document.createElement('span');
        label.className = 'small text-secondary me-1';
        label.textContent = page.dataset.textPartiesLabel || '';
        const sessionReference = document.createElement('span');
        sessionReference.className = 'badge text-bg-light me-1';
        sessionReference.textContent = `${page.dataset.textSessionReference || ''} #${dispensingSessionId.slice(0, 8)}`.trim();
        sessionReference.title = dispensingSessionId;
        partySelector.append(label, sessionReference);
        for (const party of dispensingParties) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `btn btn-sm ${Number(party.id) === activePartyId ? 'btn-primary' : 'btn-outline-secondary'}`;
            button.textContent = party.name;
            button.title = party.customer_code || party.name;
            button.setAttribute('aria-pressed', Number(party.id) === activePartyId ? 'true' : 'false');
            button.addEventListener('click', () => activateParty(party.id));
            partySelector.append(button);
        }
        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-sm btn-outline-success';
        addButton.textContent = page.dataset.textAddParty || '';
        addButton.addEventListener('click', () => patientInput?.focus());
        partySelector.append(addButton);
    }

    function activateParty(partyId) {
        const party = dispensingParties.find((item) => Number(item.id) === Number(partyId));
        if (!party) return;
        activePartyId = Number(party.id);
        try { sessionStorage.setItem(`${sessionStorageKey}-party`, String(activePartyId)); } catch {}
        page.dataset.patientCode = party.customer_code || '';
        hideSuggestions();
        const currentName = document.querySelector('.dispense-selected-patient');
        if (currentName) currentName.textContent = party.name;
        const patientDetails = document.querySelector('.dispense-patientbar .dispense-selected-patient + span');
        const patientActions = document.querySelector('.dispense-patientbar .ms-auto');
        const partyMatchesPageCustomer = Boolean(party.customer_code) && party.customer_code === initialPageCustomerCode;
        if (patientDetails) patientDetails.classList.toggle('d-none', !partyMatchesPageCustomer);
        if (patientActions) patientActions.classList.toggle('d-none', !partyMatchesPageCustomer);
        applyDispenseStatus();
        renderPartySelector();
        renderBasket();
    }

    async function initializeDispensingSession() {
        let previouslyActivePartyId = 0;
        let previouslyDisplayedCustomer = '';
        try {
            dispensingSessionId = sessionStorage.getItem(sessionStorageKey) || '';
            previouslyActivePartyId = Number(sessionStorage.getItem(`${sessionStorageKey}-party`) || 0);
            previouslyDisplayedCustomer = sessionStorage.getItem(`${sessionStorageKey}-page-customer`) || '';
        } catch {}
        if (!/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(dispensingSessionId)) {
            dispensingSessionId = window.crypto && typeof window.crypto.randomUUID === 'function'
                ? window.crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
                    const random = Math.floor(Math.random() * 16);
                    return (character === 'x' ? random : ((random & 3) | 8)).toString(16);
                });
        }
        const session = await sessionRequest('load', {
            customer_code: page.dataset.patientCode || '',
            status_key: dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'prescription',
        });
        try { sessionStorage.setItem(sessionStorageKey, dispensingSessionId); } catch {}
        dispensingParties = session.parties || [];
        for (const line of session.lines || []) basket.set(String(line.id), line);
        if (session.status_key && Array.from(dispenseStatusSelect?.options || []).some((option) => option.value === session.status_key)) {
            dispenseStatusSelect.value = session.status_key;
            page.dataset.dispenseStatus = session.status_key;
        }
        const pageCustomer = page.dataset.patientCode || '';
        const customerChanged = pageCustomer !== previouslyDisplayedCustomer;
        const preferredParty = (customerChanged && pageCustomer
            ? dispensingParties.find((party) => party.customer_code === pageCustomer)
            : null)
            || dispensingParties.find((party) => Number(party.id) === previouslyActivePartyId)
            || dispensingParties.find((party) => pageCustomer && party.customer_code === pageCustomer)
            || dispensingParties.find((party) => party.type === 'anonymous')
            || dispensingParties[0];
        try { sessionStorage.setItem(`${sessionStorageKey}-page-customer`, pageCustomer); } catch {}
        if (preferredParty) activateParty(preferredParty.id);
        else {
            applyDispenseStatus();
            renderBasket();
        }
    }

    const sessionReady = initializeDispensingSession().catch((error) => {
        showSessionError();
        if (input) input.disabled = true;
    });

    function productSuggestionButtons() {
        return [...suggestions.querySelectorAll('button.product-suggestion')];
    }

    function setActiveSuggestion(index) {
        const buttons = productSuggestionButtons();
        if (!buttons.length) return;
        activeSuggestionIndex = Math.max(0, Math.min(index, buttons.length - 1));
        buttons.forEach((button, buttonIndex) => {
            const active = buttonIndex === activeSuggestionIndex;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        input.setAttribute('aria-activedescendant', buttons[activeSuggestionIndex].id);
        buttons[activeSuggestionIndex].scrollIntoView({ block: 'nearest' });
    }

    function hideSuggestions() {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
        activeSuggestionIndex = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function renderBasket() {
        rows.replaceChildren();
        const products = Array.from(basket.values());
        count.textContent = String(products.length);
        summary.textContent = t.productCount.replace('{count}', String(products.length));
        if (!dispensingParties.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'empty-basket';
            td.textContent = t.empty;
            tr.append(td);
            rows.append(tr);
            summary.textContent = t.empty;
            return;
        }
        for (const party of dispensingParties) {
            const partyProducts = products.filter((product) => Number(product.party_id) === Number(party.id));
            const headingRow = document.createElement('tr');
            headingRow.className = 'dispense-party-heading';
            const heading = document.createElement('th');
            heading.colSpan = 4;
            heading.scope = 'rowgroup';
            heading.textContent = party.name;
            headingRow.append(heading);
            rows.append(headingRow);

            if (!partyProducts.length) {
                const emptyRow = document.createElement('tr');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 4;
                emptyCell.className = 'empty-basket';
                emptyCell.textContent = t.empty;
                emptyRow.append(emptyCell);
                rows.append(emptyRow);
                continue;
            }

            for (const product of partyProducts) {
                const tr = document.createElement('tr');
                const name = document.createElement('td');
                const strong = document.createElement('strong');
                strong.textContent = (language === 'fr' ? product.name_fr : product.name_nl)
                    || product.name_nl || product.name_fr || '';
                const small = document.createElement('small');
                const packageText = (language === 'fr' ? product.package_fr : product.package_nl)
                    || product.package_nl || product.package_fr || '';
                small.textContent = `${product.cnk} · ${packageText}`;
                name.append(strong, small);
                const statusCell = document.createElement('td');
                const statusBadge = document.createElement('span');
                statusBadge.className = 'badge text-bg-light';
                statusBadge.textContent = product.statusShortCode;
                statusCell.append(statusBadge);
                const quantity = document.createElement('td');
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.min = '1';
                quantityInput.max = '999';
                quantityInput.value = String(product.quantity);
                quantityInput.className = 'form-control form-control-sm basket-qty';
                quantityInput.setAttribute('aria-label', t.quantity);
                quantityInput.addEventListener('change', async () => {
                    const quantityValue = Math.min(999, Math.max(1, Number.parseInt(quantityInput.value, 10) || 1));
                    try {
                        await sessionRequest('line_update', { line_id: product.id, quantity: quantityValue });
                        product.quantity = quantityValue;
                        renderBasket();
                    } catch {
                        showSessionError();
                        renderBasket();
                    }
                });
                quantity.append(quantityInput);
                const removeCell = document.createElement('td');
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'basket-remove';
                remove.textContent = '×';
                remove.title = t.remove;
                remove.addEventListener('click', async () => {
                    try {
                        await sessionRequest('line_remove', { line_id: product.id });
                        basket.delete(String(product.id));
                        renderBasket();
                    } catch {
                        showSessionError();
                    }
                });
                removeCell.append(remove);
                tr.append(name, statusCell, quantity, removeCell);
                rows.append(tr);
            }
        }
    }

    async function addProduct(product) {
        await sessionReady;
        if (!activePartyId) {
            showSessionError();
            return;
        }
        const status = dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'prescription';
        try {
            const data = await sessionRequest('line_add', {
                party_id: activePartyId,
                status_key: status,
                cnk: product.cnk,
                amp_code: product.amp_code,
                ampp_code: product.ampp_code,
            });
            basket.set(String(data.line.id), data.line);
            renderBasket();
            input.value = '';
            input.focus();
            hideSuggestions();
        } catch {
            showSessionError();
        }
    }

    function exactIdentifierProduct(data, query) {
        const results = data.results || [];
        if (results.length !== 1 || Number(data.total_count ?? results.length) !== 1) return null;

        const product = results[0];
        const matchType = String(product.match_type || '').toLowerCase();
        const identifier = matchType === 'cnk'
            ? String(product.cnk || '')
            : (matchType === 'gtin' ? String(product.gtin || '') : '');
        if (!identifier) return null;

        const normalize = (value) => String(value).replace(/[\s.-]/g, '');
        const normalizedQuery = normalize(query);
        const normalizedIdentifier = normalize(identifier);
        const validLength = matchType === 'cnk'
            ? /^[0-9]{6,7}$/.test(normalizedQuery)
            : /^[0-9]{8,14}$/.test(normalizedQuery);
        return validLength && normalizedQuery === normalizedIdentifier ? product : null;
    }

    function applyDispenseStatus() {
        if (!dispenseStatusSelect) return;
        const status = dispenseStatusSelect.value || 'prescription';
        const freeDispensing = status === 'free_sale';
        page.dataset.dispenseStatus = status;
        page.dataset.dispenseMode = freeDispensing ? 'walk_in' : 'patient';
        if (patientStatusInput) patientStatusInput.value = status;
        if (currentDispenseStatus) currentDispenseStatus.textContent = dispenseStatusSelect.selectedOptions[0]?.textContent || status;
        if (input) input.disabled = !freeDispensing && !page.dataset.patientCode;
        if (patientRequiredMessage) patientRequiredMessage.classList.toggle('d-none', freeDispensing || Boolean(page.dataset.patientCode));
    }

    dispenseStatusSelect?.addEventListener('change', () => {
        applyDispenseStatus();
        void sessionReady.then(() => sessionRequest('status_set', { status_key: dispenseStatusSelect.value }))
            .catch(showSessionError);
    });
    applyDispenseStatus();

    function showResults(data) {
        suggestions.replaceChildren();
        activeSuggestionIndex = -1;
        for (const [index, product] of (data.results || []).entries()) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action product-suggestion';
            button.id = `dispense-product-option-${index}`;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');
            button.tabIndex = -1;
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
            button.addEventListener('click', () => { void addProduct(product); });
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
                url.searchParams.set('product_suggest', '1');
                url.searchParams.set('q', query);
                url.searchParams.set('mode', page.dataset.dispenseMode || 'patient');
                url.searchParams.set('dispense_status', page.dataset.dispenseStatus || 'prescription');
                if (page.dataset.dispenseMode === 'patient' && page.dataset.patientCode) {
                    url.searchParams.set('patient', page.dataset.patientCode);
                }
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: requestController.signal });
                if (!response.ok) throw new Error('search_failed');
                const data = await response.json();
                if (input.value.trim() !== query) return;
                const exactProduct = exactIdentifierProduct(data, query);
                if (exactProduct) {
                    addProduct(exactProduct);
                    return;
                }
                showResults(data);
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
    input.addEventListener('keydown', (event) => {
        const buttons = productSuggestionButtons();
        if (event.key === 'Escape') {
            if (!suggestions.classList.contains('d-none')) {
                event.preventDefault();
                hideSuggestions();
            }
            return;
        }
        if (suggestions.classList.contains('d-none') || buttons.length === 0) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveSuggestion(activeSuggestionIndex < 0 ? 0 : activeSuggestionIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveSuggestion(activeSuggestionIndex < 0 ? buttons.length - 1 : activeSuggestionIndex - 1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            setActiveSuggestion(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            setActiveSuggestion(buttons.length - 1);
        } else if (event.key === 'Enter' && activeSuggestionIndex >= 0) {
            event.preventDefault();
            buttons[activeSuggestionIndex].click();
        }
    });
    document.addEventListener('click', (event) => {
        if (!suggestions.contains(event.target) && event.target !== input) hideSuggestions();
    });

    const functionBar = document.querySelector('[data-function-bar]');
    if (functionBar) {
        const functionLayers = [...functionBar.querySelectorAll('[data-function-layer]')];
        const functionButtons = [...functionBar.querySelectorAll('[data-function-key]')];
        const layerControls = functionBar.querySelector('[data-function-layer-controls]');
        const layerSelectButtons = [...(layerControls?.querySelectorAll('[data-select-function-layer]') || [])];
        const touchLayerSelection = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        let selectedTouchLayer = 'normal';

        function updateFunctionLayer(event = {}) {
            const hasPhysicalModifier = event.ctrlKey || event.shiftKey;
            const layer = event.ctrlKey && !event.shiftKey
                ? 'ctrl'
                : (event.shiftKey && !event.ctrlKey
                    ? 'shift'
                    : (touchLayerSelection && !hasPhysicalModifier ? selectedTouchLayer : 'normal'));
            for (const element of functionLayers) {
                element.classList.toggle('d-none', element.dataset.functionLayer !== layer);
            }
            for (const button of layerSelectButtons) {
                const isActive = button.dataset.selectFunctionLayer === layer;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        }

        if (touchLayerSelection && layerControls) {
            functionBar.classList.add('has-touch-controls');
            layerControls.classList.remove('d-none');
            for (const button of layerSelectButtons) {
                button.addEventListener('click', () => {
                    const requestedLayer = button.dataset.selectFunctionLayer;
                    selectedTouchLayer = requestedLayer === selectedTouchLayer && requestedLayer !== 'normal'
                        ? 'normal'
                        : requestedLayer;
                    updateFunctionLayer();
                });
            }
        }

        function runFunctionAction(actionKey, actionConfig = {}) {
            if (actionKey.startsWith('open_page:')) {
                const modulePath = actionKey.slice('open_page:'.length);
                if (/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\.php$/.test(modulePath)) {
                    window.location.assign(new URL(modulePath, window.location.href).href);
                }
                return;
            }

            if (actionKey === 'open_url' || actionKey === 'open_company_website') {
                const url = String(actionConfig.url || '').trim();
                if (!url || /[\u0000-\u0020\u007f\\]/.test(url)) return;
                let destination;
                try {
                    destination = new URL(url, window.location.href);
                } catch {
                    return;
                }
                if (!['http:', 'https:'].includes(destination.protocol)) return;
                if (actionConfig.new_tab !== false) {
                    window.open(destination.href, '_blank', 'noopener,noreferrer');
                } else {
                    window.location.assign(destination.href);
                }
                return;
            }

            switch (actionKey) {
                case 'focus_customer_search':
                    patientInput?.focus();
                    break;
                case 'focus_product_search':
                    input.focus();
                    break;
                case 'open_customer_modal':
                    if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
                    break;
                case 'toggle_customer_regex':
                    if (patientRegex) {
                        patientRegex.checked = !patientRegex.checked;
                        patientRegex.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    break;
                case 'clear_product_search':
                    input.value = '';
                    hideSuggestions();
                    input.focus();
                    break;
                default:
                    break;
            }
        }

        function readFunctionActionConfig(button) {
            try {
                const config = JSON.parse(button.dataset.actionConfig || '{}');
                return config && typeof config === 'object' ? config : {};
            } catch {
                return {};
            }
        }

        for (const button of functionButtons) {
            button.addEventListener('click', () => runFunctionAction(button.dataset.actionKey, readFunctionActionConfig(button)));
        }

        document.addEventListener('keydown', (event) => {
            updateFunctionLayer(event);
            const functionKey = /^F(1[0-2]|[1-9])$/.exec(event.key);
            if (!functionKey || event.altKey || event.metaKey || (event.ctrlKey && event.shiftKey)) return;
            const prefix = event.ctrlKey ? 'CTRL_' : (event.shiftKey ? 'SHIFT_' : '');
            const keyCode = `${prefix}F${functionKey[1]}`;
            const shortcut = functionButtons.find((button) => button.dataset.keyCode === keyCode);
            if (!shortcut || shortcut.dataset.actionKey === 'none') return;
            event.preventDefault();
            runFunctionAction(shortcut.dataset.actionKey, readFunctionActionConfig(shortcut));
        }, true);

        document.addEventListener('keyup', updateFunctionLayer);
        window.addEventListener('blur', () => updateFunctionLayer());
    }
})();
