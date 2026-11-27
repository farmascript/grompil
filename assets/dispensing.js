(() => {
    const page = document.querySelector('[data-dispense-page]');
    if (!page) return;

    const modal = document.getElementById('patientModal');
    const nationalNumberInput = modal?.querySelector('#patient-national-register-number');
    const birthDateInput = modal?.querySelector('#patient-dob');
    const birthDateFromNationalNumber = (value) => {
        if (!/^[0-9.\s-]+$/.test(value)) return null;
        const digits = value.replace(/[.\s-]+/g, '');
        if (!/^\d{11}$/.test(digits)) return null;

        const firstNine = digits.slice(0, 9);
        const checkDigits = Number.parseInt(digits.slice(9, 11), 10);
        const shortYear = Number.parseInt(digits.slice(0, 2), 10);
        let year;
        if (checkDigits === 97 - (Number.parseInt(firstNine, 10) % 97)) {
            year = 1900 + shortYear;
        } else if (checkDigits === 97 - (Number.parseInt(`2${firstNine}`, 10) % 97)) {
            year = 2000 + shortYear;
        } else {
            return null;
        }

        const month = Number.parseInt(digits.slice(2, 4), 10);
        const day = Number.parseInt(digits.slice(4, 6), 10);
        const date = new Date(Date.UTC(year, month - 1, day));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1
            || date.getUTCDate() !== day || year > new Date().getFullYear()) return null;
        return `${year.toString().padStart(4, '0')}-${digits.slice(2, 4)}-${digits.slice(4, 6)}`;
    };
    const updateBirthDateFromNationalNumber = () => {
        if (!nationalNumberInput || !birthDateInput) return;
        const value = nationalNumberInput.value.trim();
        if (value === '') {
            nationalNumberInput.setCustomValidity('');
            birthDateInput.readOnly = false;
            return;
        }
        const birthDate = birthDateFromNationalNumber(value);
        nationalNumberInput.setCustomValidity(birthDate ? '' : (nationalNumberInput.dataset.invalidMessage || ''));
        if (birthDate) {
            birthDateInput.value = birthDate;
            birthDateInput.readOnly = true;
        } else {
            birthDateInput.readOnly = false;
        }
    };
    nationalNumberInput?.addEventListener('input', updateBirthDateFromNationalNumber);
    updateBirthDateFromNationalNumber();
    modal?.addEventListener('shown.bs.modal', () => {
        const action = modal.querySelector('.customer-edit-form input[name="action"]')?.value;
        if (action === 'create_patient') {
            modal.querySelector('#patient-first-name')?.focus();
        }
    });
    if (modal && page.dataset.modalOpen === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    const duplicateWarningModal = document.getElementById('patientDuplicateWarningModal');
    if (duplicateWarningModal && page.dataset.duplicateWarning === 'true' && window.bootstrap) {
        const warningInstance = bootstrap.Modal.getOrCreateInstance(duplicateWarningModal);
        duplicateWarningModal.addEventListener('hidden.bs.modal', () => {
            if (modal && page.dataset.duplicateSubmitted !== 'true') {
                bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
        duplicateWarningModal.querySelector('[data-confirm-duplicate-patient]')?.addEventListener('click', () => {
            const confirmation = document.getElementById('confirm-duplicate-patient');
            const patientForm = modal?.querySelector('.customer-edit-form');
            if (!confirmation || !patientForm) return;
            confirmation.value = '1';
            page.dataset.duplicateSubmitted = 'true';
            patientForm.requestSubmit();
        });
        warningInstance.show();
    }

    const contactModal = document.getElementById('contact-manager-modal');
    if (contactModal && page.dataset.contactModalOpen === 'true' && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(contactModal).show();
    }
    const input = document.querySelector('[data-product-search]');
    const withdrawnToggle = document.querySelector('[data-include-revoked]');
    const suggestions = document.querySelector('[data-product-suggestions]');
    const rows = document.querySelector('[data-basket-rows]');
    const count = document.querySelector('[data-basket-count]');
    const summary = document.querySelector('[data-basket-summary]');
    const partySelector = document.querySelector('[data-dispense-parties]');
    const activePartyLabel = document.querySelector('[data-dispense-active-party]');
    const sessionReferenceLabel = document.querySelector('[data-session-reference]');
    const copySessionButton = document.querySelector('[data-copy-session]');
    const closeDispensingButton = document.querySelector('[data-close-dispensing]');
    const receiptModalElement = document.getElementById('dispensingReceiptModal');
    const receiptFrame = document.querySelector('[data-receipt-frame]');
    const receiptError = document.querySelector('[data-receipt-error]');
    const receiptConfirmButton = document.querySelector('[data-receipt-confirm]');
    const discardModalElement = document.getElementById('dispensingDiscardConfirmModal');
    const discardConfirmButton = document.querySelector('[data-discard-confirm]');
    const discardError = document.querySelector('[data-discard-error]');
    const lineEditModalElement = document.getElementById('dispensingLineEditModal');
    const lineEditForm = document.querySelector('[data-line-edit-form]');
    const lineEditTitle = document.querySelector('[data-line-edit-title]');
    const lineEditParty = document.querySelector('[data-line-edit-party]');
    const lineEditStatus = document.querySelector('[data-line-edit-status]');
    const lineEditQuantity = document.querySelector('[data-line-edit-quantity]');
    const lineEditError = document.querySelector('[data-line-edit-error]');
    const lineEditSave = document.querySelector('[data-line-edit-save]');
    const lineCaregiverWrap = document.querySelector('[data-line-caregiver-wrap]');
    const lineCaregiverInput = document.querySelector('[data-line-caregiver-search]');
    const lineCaregiverCode = document.querySelector('[data-line-caregiver-code]');
    const lineCaregiverResults = document.querySelector('[data-line-caregiver-results]');
    const missingCaregiverModal = document.getElementById('dispensingMissingCaregiverModal');
    const missingCaregiverLines = document.querySelector('[data-missing-caregiver-lines]');
    const missingCaregiverError = document.querySelector('[data-missing-caregiver-error]');
    const missingCaregiverContinue = document.querySelector('[data-missing-caregiver-continue]');
    const newPrescriptionModalElement = document.getElementById('dispensingNewPrescriptionModal');
    const newPrescriptionTitle = document.querySelector('[data-new-prescription-title]');
    const newPrescriptionKeepLabel = document.querySelector('[data-new-prescription-keep-label]');
    const newPrescriptionCaregiverInput = document.querySelector('[data-new-prescription-caregiver-search]');
    const newPrescriptionCaregiverCode = document.querySelector('[data-new-prescription-caregiver-code]');
    const newPrescriptionCaregiverResults = document.querySelector('[data-new-prescription-caregiver-results]');
    const newPrescriptionError = document.querySelector('[data-new-prescription-error]');
    const newPrescriptionSave = document.querySelector('[data-new-prescription-save]');
    let pendingPrescriptionStatusKey = '';
    let editingLine = null;
    const historyModalElement = document.getElementById('dispensingPatientHistoryModal');
    const historyBody = document.querySelector('[data-patient-history-body]');
    const historyTitle = document.querySelector('[data-patient-history-title]');
    const historySearch = document.querySelector('[data-patient-history-search]');
    const historyPageSize = document.querySelector('[data-patient-history-page-size]');
    const historyPagination = document.querySelector('[data-patient-history-pagination]');
    const historyCount = document.querySelector('[data-patient-history-count]');
    let receiptObjectUrl = '';

    const patientInput = document.querySelector('[data-patient-search-input]');
    const patientForm = document.querySelector('[data-patient-search-form]');
    const patientSuggestions = document.querySelector('[data-patient-suggestions]');
    const patientStatus = document.querySelector('[data-patient-search-status]');
    const patientRegex = document.querySelector('[data-patient-regex]');
    const patientIncludeDeceased = document.querySelector('[data-patient-include-deceased]');
    const dispenseStatusSelect = document.querySelector('[data-dispense-status-select]');
    let lastPersistedDispenseStatus = dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale';
    const patientSearchShortcuts = [];
    function registerPatientSearchShortcut(match) {
        if (typeof match === 'function') patientSearchShortcuts.push(match);
    }
    function findPatientSearchShortcut(value) {
        if ([...value].length !== 1) return null;
        for (const match of patientSearchShortcuts) {
            const shortcut = match(value);
            if (shortcut) return shortcut;
        }
        return null;
    }
    registerPatientSearchShortcut((value) => {
        const option = [...(dispenseStatusSelect?.options || [])].find((item) => {
            const code = String(item.dataset.shortCode || '').trim();
            return [...code].length === 1 && code.toLocaleLowerCase() === value.toLocaleLowerCase();
        });
        return option ? {
            run: () => {
                dispenseStatusSelect.value = option.value;
                dispenseStatusSelect.dispatchEvent(new Event('change', { bubbles: true }));
            },
        } : null;
    });
    const patientStatusInput = document.querySelector('[data-patient-status-input]');
    const patientRequiredMessage = document.querySelector('[data-dispense-patient-required]');
    const initialPageCustomerCode = page.dataset.patientCode || '';
    let patientTimer = 0;
    let patientRequestController = null;
    let activePatientSuggestionIndex = -1;

    function patientSuggestionLinks() {
        return [...(patientSuggestions?.querySelectorAll('a.patient-suggestion') || [])];
    }

    function setActivePatientSuggestion(index) {
        const links = patientSuggestionLinks();
        if (!links.length) return;
        activePatientSuggestionIndex = Math.max(0, Math.min(index, links.length - 1));
        links.forEach((link, linkIndex) => {
            const active = linkIndex === activePatientSuggestionIndex;
            link.classList.toggle('is-keyboard-active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        const activeLink = links[activePatientSuggestionIndex];
        patientInput?.setAttribute('aria-activedescendant', activeLink.id);
        activeLink.scrollIntoView({ block: 'nearest' });
    }

    function preparePatientSuggestionOptions() {
        patientSuggestionLinks().forEach((link, index) => {
            if (!link.id) link.id = `patient-option-${index}`;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', 'false');
            link.tabIndex = -1;
        });
    }

    function hidePatientSuggestions() {
        if (!patientSuggestions || !patientInput) return;
        patientSuggestions.classList.add('d-none');
        patientSuggestions.replaceChildren();
        activePatientSuggestionIndex = -1;
        patientInput.setAttribute('aria-expanded', 'false');
        patientInput.removeAttribute('aria-activedescendant');
    }

    function setPatientStatus(message) {
        if (patientStatus) patientStatus.textContent = message;
    }

    function showPatientSuggestions(results) {
        patientSuggestions.replaceChildren();
        activePatientSuggestionIndex = -1;
        if (!results.length) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item small text-secondary';
            empty.textContent = page.dataset.textPatientNoResults || '';
            patientSuggestions.append(empty);
        } else {
            for (const [index, patient] of results.entries()) {
                const url = new URL(page.dataset.patientEndpoint, window.location.href);
                url.searchParams.set('patient', String(patient.customer_code || ''));
                url.searchParams.set('mode', 'patient');
                url.searchParams.set('dispense_status', dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale');
                if (patientIncludeDeceased?.checked) url.searchParams.set('include_deceased', '1');
                const link = document.createElement('a');
                link.className = 'list-group-item list-group-item-action patient-suggestion';
                link.id = `patient-option-${index}`;
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');
                link.tabIndex = -1;
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
                if (patient.is_deceased) {
                    link.classList.add('customer-deceased-option');
                    const deathDate = document.createElement('span');
                    deathDate.className = 'd-block small customer-deceased-date';
                    const age = Number.isInteger(patient.age_at_death) ? ` (${patient.age_at_death})` : '';
                    deathDate.textContent = [patient.deceased_label, `${patient.death_date_display || ''}${age}`]
                        .filter(Boolean).join(' · ');
                    link.append(deathDate);
                }
                patientSuggestions.append(link);
            }
        }
        preparePatientSuggestionOptions();
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
                if (patientIncludeDeceased?.checked) url.searchParams.set('include_deceased', '1');
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
        patientIncludeDeceased?.addEventListener('change', schedulePatientSearch);
        patientForm.addEventListener('submit', (event) => {
            if (patientInput.value.trim().length < Number(page.dataset.patientMinChars || 3)) {
                event.preventDefault();
                setPatientStatus(page.dataset.textPatientMinimum || '');
            }
        });
        patientInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hidePatientSuggestions();
                return;
            }
            if (event.key === 'Enter') {
                const shortcut = findPatientSearchShortcut(patientInput.value.trim());
                if (shortcut) {
                    event.preventDefault();
                    window.clearTimeout(patientTimer);
                    if (patientRequestController) patientRequestController.abort();
                    patientInput.value = '';
                    hidePatientSuggestions();
                    setPatientStatus('');
                    shortcut.run();
                    return;
                }
            }
            if (patientSuggestions.classList.contains('d-none')) return;
            const links = patientSuggestionLinks();
            if (!links.length) return;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActivePatientSuggestion(activePatientSuggestionIndex < 0 ? 0 : activePatientSuggestionIndex + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActivePatientSuggestion(activePatientSuggestionIndex < 0 ? links.length - 1 : activePatientSuggestionIndex - 1);
            } else if (event.key === 'Home') {
                event.preventDefault();
                setActivePatientSuggestion(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                setActivePatientSuggestion(links.length - 1);
            } else if (event.key === 'Enter' && activePatientSuggestionIndex >= 0) {
                event.preventDefault();
                links[activePatientSuggestionIndex].click();
            }
        });
        document.addEventListener('click', (event) => {
            if (!patientSuggestions.contains(event.target) && event.target !== patientInput) {
                hidePatientSuggestions();
            }
        });
    }

    if (patientSuggestions) {
        preparePatientSuggestionOptions();
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
                activateParty(data.active_party_id, true);
                patientInput.value = '';
                hidePatientSuggestions();
                setPatientStatus('');
                input?.focus();
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
        lineEdit: page.dataset.textLineEdit,
        lineEditSave: page.dataset.textLineEditSave,
        lineEditError: page.dataset.textLineEditError,
        caregiverSearch: page.dataset.textCaregiverSearch,
        caregiverMinimum: page.dataset.textCaregiverMinimum,
        caregiverUnavailable: page.dataset.textCaregiverUnavailable,
        caregiverMissingMessage: page.dataset.textCaregiverMissingMessage,
        caregiverContinue: page.dataset.textCaregiverContinue,
        caregiverSelected: page.dataset.textCaregiverSelected,
        caregiverAdd: page.dataset.textCaregiverAdd,
        newPrescription: page.dataset.textNewPrescription,
        prescriptionKeepCaregiver: page.dataset.textPrescriptionKeepCaregiver,
        prescriptionChooseCaregiver: page.dataset.textPrescriptionChooseCaregiver,
        prescriptionStart: page.dataset.textPrescriptionStart,
        productCount: page.dataset.textProductCount,
        discardConfirm: page.dataset.textDiscardConfirm,
        historyTitle: page.dataset.textHistoryTitle,
        historyAnonymousTitle: historyTitle?.dataset.anonymousTitle,
        historyPrescriptionNumber: historyTitle?.dataset.prescriptionNumberLabel,
        historyError: page.dataset.textHistoryError,
        historyEmpty: page.dataset.textHistoryEmpty,
        historyDate: page.dataset.textHistoryDate,
        historySession: page.dataset.textHistorySession,
        historyStatus: page.dataset.textHistoryStatus,
        historyCnk: page.dataset.textHistoryCnk,
        historyProduct: page.dataset.textHistoryProduct,
        historyPackage: page.dataset.textHistoryPackage,
        historyQuantity: page.dataset.textHistoryQuantity,
        historyPrevious: page.dataset.textHistoryPrevious,
        historyNext: page.dataset.textHistoryNext,
        historyFirst: page.dataset.textHistoryFirst,
        historyLast: page.dataset.textHistoryLast,
    };
    const language = (page.dataset.lang || 'nl').toLowerCase();
    const basket = new Map();
    let dispensingSessionId = '';
    let dispensingParties = [];
    let activePartyId = 0;
    const caregiverByParty = new Map();
    let pendingSessionCaregiver = null;
    let timer = 0;
    let requestController = null;
    let activeSuggestionIndex = -1;

    const sessionStorageKey = `grompil-dispensing-${page.dataset.sessionCompany || '0'}-${page.dataset.sessionUser || 'user'}`;
    const withdrawnFilterStorageKey = () => `${sessionStorageKey}-${dispensingSessionId}-include-revoked`;
    const prescriptionCaregiversStorageKey = () => `${sessionStorageKey}-${dispensingSessionId}-prescription-caregivers`;
    const pendingCaregiverStorageKey = () => `${sessionStorageKey}-${dispensingSessionId}-pending-caregiver`;

    function saveCaregiverDefaults() {
        try {
            sessionStorage.setItem(prescriptionCaregiversStorageKey(), JSON.stringify(Object.fromEntries(caregiverByParty)));
        } catch {}
    }

    function setPartyCaregiver(partyId, caregiver) {
        if (!partyId || !caregiver?.caregiver_code) return;
        caregiverByParty.set(String(partyId), {
            caregiver_code: String(caregiver.caregiver_code),
            name: String(caregiver.name || ''),
        });
        saveCaregiverDefaults();
        if (Number(partyId) === activePartyId) renderActivePartyLabel();
    }

    let historyCustomerCode = '';
    let historyCurrentPage = 1;
    let historyRequestId = 0;
    let historySearchTimer = 0;
    async function loadPatientHistory() {
        if (!historyBody) return;
        const requestId = ++historyRequestId;
        historyBody.textContent = '…';
        const params = new URLSearchParams({
            history_api: '1', scope: historyCustomerCode ? 'customer' : 'anonymous', customer_code: historyCustomerCode,
            q: historySearch?.value || '', page: String(historyCurrentPage),
            per_page: String(historyPageSize?.value || 25),
        });
        try {
            const response = await fetch(`dispensing-history.php?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('History unavailable');
            const data = await response.json();
            if (requestId !== historyRequestId) return;
            historyBody.replaceChildren();
            if (!data.rows?.length) {
                historyBody.textContent = t.historyEmpty || '';
            } else {
                const table = document.createElement('table');
                table.className = 'table table-sm table-striped align-middle';
                const headings = [t.historyDate, t.historySession, t.historyPrescriptionNumber, t.historyStatus, t.historyCnk, t.historyProduct, t.historyQuantity];
                const thead = table.createTHead().insertRow();
                headings.forEach((label) => { const th = document.createElement('th'); th.textContent = label || ''; thead.append(th); });
                const tbody = table.createTBody();
                data.rows.forEach((row) => {
                    const tr = tbody.insertRow();
                    const product = language === 'fr' ? (row.product_name_fr || row.product_name_nl) : (row.product_name_nl || row.product_name_fr);
                    const pack = language === 'fr' ? (row.package_display_fr || row.package_display_nl) : (row.package_display_nl || row.package_display_fr);
                    [row.completed_at_display, String(row.session_id).slice(0, 8) + '…', row.prescription_number === null ? '—' : `#${row.prescription_number}`, row.status_code, row.cnk].forEach((value) => {
                        const cell = tr.insertCell(); cell.textContent = String(value ?? '');
                    });
                    const productCell = tr.insertCell();
                    const productLabel = document.createElement('strong');
                    productLabel.textContent = String(product ?? '');
                    productCell.append(productLabel);
                    if (pack) {
                        const packageLabel = document.createElement('strong');
                        packageLabel.className = 'ms-2';
                        packageLabel.textContent = pack;
                        productCell.append(packageLabel);
                    }
                    const quantityCell = tr.insertCell();
                    quantityCell.className = 'text-end';
                    quantityCell.textContent = String(row.quantity ?? '');
                });
                const responsiveTable = document.createElement('div');
                responsiveTable.className = 'table-responsive';
                responsiveTable.append(table);
                historyBody.append(responsiveTable);
            }
            if (historyCount) historyCount.textContent = String(data.total || 0);
            if (historyPagination) {
                historyPagination.replaceChildren();
                const items = [[1, t.historyFirst], [Math.max(1, data.page - 1), t.historyPrevious], [Math.min(data.pages, data.page + 1), t.historyNext], [data.pages, t.historyLast]];
                items.forEach(([target, label], index) => {
                    const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-secondary'; button.textContent = label || '';
                    button.disabled = (index < 2 && data.page === 1) || (index >= 2 && data.page >= data.pages);
                    button.addEventListener('click', () => { historyCurrentPage = Number(target); void loadPatientHistory(); });
                    historyPagination.append(button);
                });
            }
        } catch {
            if (requestId === historyRequestId) historyBody.textContent = t.historyError || '';
        }
    }

    function openPatientHistory() {
        historyCustomerCode = page.dataset.patientCode || '';
        historyCurrentPage = 1;
        if (!historyCustomerCode) {
            if (historyTitle) historyTitle.textContent = t.historyAnonymousTitle || t.historyTitle || '';
            if (historySearch) historySearch.value = '';
            if (historyCount) historyCount.textContent = '0';
            historyPagination?.replaceChildren();
        } else {
            if (historyTitle) historyTitle.textContent = `${t.historyTitle || ''} — ${page.dataset.patientName || historyCustomerCode}`;
            if (historySearch) historySearch.value = '';
        }
        if (historyModalElement && window.bootstrap) bootstrap.Modal.getOrCreateInstance(historyModalElement).show();
        void loadPatientHistory();
    }

    historySearch?.addEventListener('input', () => {
        window.clearTimeout(historySearchTimer);
        historySearchTimer = window.setTimeout(() => {
            const query = historySearch.value.trim();
            historyCurrentPage = 1;
            if (query.length > 0 && query.length < 3) {
                historyRequestId += 1;
                if (historyBody) historyBody.textContent = historySearch.dataset.minimumMessage || '';
                if (historyCount) historyCount.textContent = '0';
                historyPagination?.replaceChildren();
                return;
            }
            void loadPatientHistory();
        }, 250);
    });
    historyPageSize?.addEventListener('change', () => { historyCurrentPage = 1; void loadPatientHistory(); });

    function renderSessionReference() {
        if (sessionReferenceLabel) {
            sessionReferenceLabel.textContent = `${page.dataset.textSessionReference || ''} #${dispensingSessionId}`.trim();
            sessionReferenceLabel.title = dispensingSessionId;
        }
    }

    async function copySessionReference() {
        const originalLabel = copySessionButton?.getAttribute('aria-label') || '';
        const copiedLabel = copySessionButton?.dataset.copiedLabel || originalLabel;
        let copied = false;
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(dispensingSessionId);
                copied = true;
            }
        } catch {}
        if (!copied) {
            const temporary = document.createElement('textarea');
            temporary.value = dispensingSessionId;
            temporary.setAttribute('readonly', '');
            temporary.style.position = 'fixed';
            temporary.style.opacity = '0';
            document.body.append(temporary);
            temporary.select();
            try { copied = document.execCommand('copy'); } catch {}
            temporary.remove();
        }
        if (copied && copySessionButton) {
            copySessionButton.setAttribute('aria-label', copiedLabel);
            copySessionButton.title = copiedLabel;
            window.setTimeout(() => {
                copySessionButton.setAttribute('aria-label', originalLabel);
                copySessionButton.title = originalLabel;
            }, 1800);
        }
    }

    copySessionButton?.addEventListener('click', () => { void copySessionReference(); });

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
        if (!response.ok) {
            const error = new Error(data.error || page.dataset.textSessionError || '');
            error.data = data;
            throw error;
        }
        return data;
    }

    function attachCaregiverSearch(searchInput, resultsElement, onSelect) {
        if (!searchInput || !resultsElement) return;
        let timer = 0;
        let requestSequence = 0;
        let activeIndex = -1;
        const hide = () => {
            resultsElement.replaceChildren();
            resultsElement.classList.add('d-none');
            searchInput.setAttribute('aria-expanded', 'false');
        };
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            hide();
            if (searchInput.dataset.selectedCode) {
                delete searchInput.dataset.selectedCode;
                onSelect(null);
            }
            if (query.length < 3) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(async () => {
                const sequence = ++requestSequence;
                try {
                    const url = new URL(page.dataset.caregiverEndpoint, window.location.href);
                    url.searchParams.set('q', query);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const data = await response.json();
                    if (sequence !== requestSequence) return;
                    const matches = (data.results || []).filter((caregiver) => ['physician', 'dentist'].includes(caregiver.type_key));
                    resultsElement.replaceChildren();
                    for (const caregiver of matches) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'list-group-item list-group-item-action';
                        option.textContent = `${caregiver.name} · ${caregiver.caregiver_code}${caregiver.identifiers ? ` · ${caregiver.identifiers}` : ''}`;
                        option.addEventListener('click', () => {
                            searchInput.value = caregiver.name;
                            searchInput.dataset.selectedCode = caregiver.caregiver_code;
                            onSelect(caregiver);
                            hide();
                        });
                        resultsElement.append(option);
                    }
                    activeIndex = -1;
                    if (matches.length === 0) {
                        const empty = document.createElement('div');
                        empty.className = 'list-group-item small text-secondary';
                        empty.textContent = t.caregiverUnavailable || '';
                        resultsElement.append(empty);
                        if (searchInput.dataset.addLabel) {
                            const hasManageAccess = Boolean(searchInput.dataset.manageUrl);
                            const addPrescriber = document.createElement(hasManageAccess ? 'a' : 'span');
                            addPrescriber.className = hasManageAccess
                                ? 'list-group-item list-group-item-action text-primary'
                                : 'list-group-item small text-secondary';
                            addPrescriber.textContent = searchInput.dataset.addLabel;
                            if (hasManageAccess) {
                                addPrescriber.href = searchInput.dataset.manageUrl;
                                addPrescriber.target = '_blank';
                                addPrescriber.rel = 'noopener noreferrer';
                            }
                            resultsElement.append(addPrescriber);
                        }
                    }
                    resultsElement.classList.remove('d-none');
                    searchInput.setAttribute('aria-expanded', 'true');
                } catch {
                    hide();
                }
            }, 220);
        });
        searchInput.addEventListener('keydown', (event) => {
            const options = Array.from(resultsElement.querySelectorAll('button'));
            if (event.key === 'ArrowDown' && options.length) {
                event.preventDefault();
                activeIndex = Math.min(activeIndex + 1, options.length - 1);
                options.forEach((option, index) => option.classList.toggle('active', index === activeIndex));
                options[activeIndex].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'ArrowUp' && options.length) {
                event.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                options.forEach((option, index) => option.classList.toggle('active', index === activeIndex));
                options[activeIndex].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
                event.preventDefault();
                options[activeIndex].click();
            }
            if (event.key === 'Escape') hide();
        });
    }

    attachCaregiverSearch(lineCaregiverInput, lineCaregiverResults, (caregiver) => {
        if (lineCaregiverCode) lineCaregiverCode.value = caregiver?.caregiver_code || '';
    });

    attachCaregiverSearch(newPrescriptionCaregiverInput, newPrescriptionCaregiverResults, (caregiver) => {
        if (newPrescriptionCaregiverCode) newPrescriptionCaregiverCode.value = caregiver?.caregiver_code || '';
    });

    function openNewPrescriptionModal(forStatusChange = false) {
        if (!newPrescriptionModalElement || !window.bootstrap) return;
        if (newPrescriptionTitle) {
            newPrescriptionTitle.textContent = forStatusChange
                ? newPrescriptionTitle.dataset.promptTitle || ''
                : newPrescriptionTitle.dataset.defaultTitle || '';
        }
        if (newPrescriptionSave) {
            newPrescriptionSave.textContent = forStatusChange
                ? newPrescriptionSave.dataset.promptLabel || ''
                : newPrescriptionSave.dataset.defaultLabel || '';
        }
        const currentParty = dispensingParties.find((party) => Number(party.id) === activePartyId);
        const currentCaregiver = caregiverByParty.get(String(activePartyId))
            || (currentParty?.type !== 'customer' ? pendingSessionCaregiver : null);
        if (newPrescriptionKeepLabel) {
            newPrescriptionKeepLabel.textContent = currentCaregiver?.name
                ? (t.prescriptionKeepCaregiver || '').replace('{name}', currentCaregiver.name)
                : (t.prescriptionChooseCaregiver || '');
        }
        if (newPrescriptionCaregiverInput) {
            newPrescriptionCaregiverInput.value = '';
            delete newPrescriptionCaregiverInput.dataset.selectedCode;
        }
        if (newPrescriptionCaregiverCode) newPrescriptionCaregiverCode.value = '';
        newPrescriptionCaregiverResults?.replaceChildren();
        newPrescriptionCaregiverResults?.classList.add('d-none');
        if (newPrescriptionError) {
            newPrescriptionError.textContent = '';
            newPrescriptionError.classList.add('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(newPrescriptionModalElement).show();
    }

    async function persistDispenseStatus(statusKey) {
        if (!dispenseStatusSelect) return;
        dispenseStatusSelect.value = statusKey;
        applyDispenseStatus();
        renderPartySelector();
        renderBasket();
        try {
            await sessionReady;
            await sessionRequest('status_set', { status_key: statusKey });
            lastPersistedDispenseStatus = statusKey;
        } catch {
            dispenseStatusSelect.value = lastPersistedDispenseStatus;
            applyDispenseStatus();
            renderPartySelector();
            renderBasket();
            showSessionError();
        }
    }

    newPrescriptionSave?.addEventListener('click', async () => {
        const selectedCode = newPrescriptionCaregiverCode?.value || '';
        const activeParty = dispensingParties.find((party) => Number(party.id) === activePartyId);
        const selectedCaregiver = selectedCode ? {
            caregiver_code: selectedCode,
            name: newPrescriptionCaregiverInput?.value || '',
        } : null;
        if (selectedCaregiver && activeParty?.type === 'customer') {
            setPartyCaregiver(activePartyId, selectedCaregiver);
        } else if (selectedCaregiver) {
            pendingSessionCaregiver = selectedCaregiver;
            try { sessionStorage.setItem(pendingCaregiverStorageKey(), JSON.stringify(pendingSessionCaregiver)); } catch {}
        }
        const currentCaregiver = selectedCaregiver || caregiverByParty.get(String(activePartyId))
            || (activeParty?.type !== 'customer' ? pendingSessionCaregiver : null);
        if (!currentCaregiver?.caregiver_code) {
            if (newPrescriptionError) {
                newPrescriptionError.textContent = t.prescriptionChooseCaregiver || t.caregiverUnavailable || '';
                newPrescriptionError.classList.remove('d-none');
            }
            newPrescriptionCaregiverInput?.focus();
            return;
        }
        const pendingStatusKey = pendingPrescriptionStatusKey;
        pendingPrescriptionStatusKey = '';
        bootstrap.Modal.getOrCreateInstance(newPrescriptionModalElement).hide();
        renderActivePartyLabel();
        if (pendingStatusKey) {
            await persistDispenseStatus(pendingStatusKey);
        } else {
            input?.focus();
        }
    });

    newPrescriptionModalElement?.addEventListener('hidden.bs.modal', () => {
        if (!pendingPrescriptionStatusKey) return;
        pendingPrescriptionStatusKey = '';
        if (dispenseStatusSelect) dispenseStatusSelect.value = lastPersistedDispenseStatus;
        applyDispenseStatus();
        renderPartySelector();
        renderBasket();
    });

    function showMissingCaregiverModal(lines) {
        if (!missingCaregiverModal || !missingCaregiverLines || !window.bootstrap) return;
        missingCaregiverLines.replaceChildren();
        if (missingCaregiverError) {
            missingCaregiverError.textContent = '';
            missingCaregiverError.classList.add('d-none');
        }
        if (missingCaregiverContinue) missingCaregiverContinue.disabled = true;
        let remaining = lines.length;
        const updateContinue = () => {
            if (missingCaregiverContinue) missingCaregiverContinue.disabled = remaining !== 0;
        };
        for (const line of lines) {
            const card = document.createElement('div');
            card.className = 'border rounded p-3 mb-2';
            const description = document.createElement('div');
            description.className = 'fw-semibold mb-2';
            description.textContent = `${line.party_name} · ${line.product_name} · ${line.cnk} · ${line.quantity}`;
            const search = document.createElement('input');
            search.type = 'search';
            search.className = 'form-control';
            search.placeholder = t.caregiverSearch || '';
            search.autocomplete = 'off';
            search.setAttribute('aria-label', t.caregiverSearch || '');
            const results = document.createElement('div');
            results.className = 'list-group mt-1 d-none';
            card.append(description, search, results);
            missingCaregiverLines.append(card);
            attachCaregiverSearch(search, results, async (caregiver) => {
                if (!caregiver) return;
                search.disabled = true;
                try {
                    const updated = await sessionRequest('line_update', {
                        line_id: line.id,
                        quantity: Number(line.quantity),
                        caregiver_code: caregiver.caregiver_code,
                    });
                    setPartyCaregiver(line.party_id, caregiver);
                    for (const [key, basketLine] of basket) {
                        if (!basketLine.pending) basket.delete(key);
                    }
                    for (const basketLine of updated.lines || []) basket.set(String(basketLine.id), basketLine);
                    renderBasket();
                    card.replaceChildren(description, Object.assign(document.createElement('div'), {
                        className: 'small text-success',
                        textContent: `${t.caregiverSelected || ''}: ${caregiver.name}`,
                    }));
                    remaining -= 1;
                    updateContinue();
                } catch {
                    search.disabled = false;
                    if (missingCaregiverError) {
                        missingCaregiverError.textContent = t.caregiverUnavailable || '';
                        missingCaregiverError.classList.remove('d-none');
                    }
                }
            });
        }
        bootstrap.Modal.getOrCreateInstance(missingCaregiverModal).show();
    }

    missingCaregiverContinue?.addEventListener('click', () => {
        bootstrap.Modal.getOrCreateInstance(missingCaregiverModal).hide();
        window.setTimeout(() => { void beginReceiptPreview(); }, 0);
    });

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
        const visibleParties = dispensingParties.filter((party) => party.type !== 'anonymous');
        for (const party of visibleParties) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `btn btn-sm ${Number(party.id) === activePartyId ? 'btn-primary' : 'btn-outline-secondary'}`;
            button.textContent = party.name;
            button.title = party.customer_code || party.name;
            button.setAttribute('aria-pressed', Number(party.id) === activePartyId ? 'true' : 'false');
            button.addEventListener('click', () => activateParty(party.id, true));
            partySelector.append(button);
        }
        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-sm btn-outline-success';
        addButton.textContent = page.dataset.textAddParty || '';
        addButton.addEventListener('click', () => patientInput?.focus());
        partySelector.append(addButton);
        renderActivePartyLabel();
    }

    function renderActivePartyLabel() {
        if (!activePartyLabel) return;
        const party = dispensingParties.find((item) => Number(item.id) === activePartyId);
        const isAnonymous = !party || party.type === 'anonymous';
        const statusLabel = document.createElement('span');
        statusLabel.className = 'status-pill';
        statusLabel.textContent = dispenseStatusSelect?.selectedOptions[0]?.textContent || page.dataset.dispenseStatus || '';
        activePartyLabel.replaceChildren();
        if (isAnonymous) {
            activePartyLabel.append(statusLabel);
        } else {
            const patientName = document.createElement('span');
            patientName.className = 'dispense-active-party-name';
            patientName.textContent = party.name || party.customer_code || '';
            activePartyLabel.append(patientName, statusLabel);
            if (dispenseStatusSelect?.selectedOptions[0]?.dataset.requiresCaregiver === 'true') {
                const caregiver = caregiverByParty.get(String(activePartyId));
                if (caregiver?.name) {
                    const caregiverLabel = document.createElement('span');
                    caregiverLabel.className = 'status-pill status-muted';
                    caregiverLabel.textContent = caregiver.name;
                    caregiverLabel.title = (t.prescriptionKeepCaregiver || '').replace('{name}', caregiver.name);
                    caregiverLabel.setAttribute('aria-label', caregiverLabel.title);
                    activePartyLabel.append(caregiverLabel);
                }
                const newPrescriptionButton = document.createElement('button');
                newPrescriptionButton.type = 'button';
                newPrescriptionButton.className = 'btn btn-sm btn-outline-primary';
                newPrescriptionButton.textContent = t.newPrescription || '';
                newPrescriptionButton.addEventListener('click', openNewPrescriptionModal);
                activePartyLabel.append(newPrescriptionButton);
            }
        }
        activePartyLabel.className = 'dispense-active-party';
    }

    function activateParty(partyId, promptForPrescriber = false) {
        const party = dispensingParties.find((item) => Number(item.id) === Number(partyId));
        if (!party) return;
        activePartyId = Number(party.id);
        if (party.type === 'customer' && pendingSessionCaregiver && !caregiverByParty.has(String(activePartyId))) {
            setPartyCaregiver(activePartyId, pendingSessionCaregiver);
            pendingSessionCaregiver = null;
            try { sessionStorage.removeItem(pendingCaregiverStorageKey()); } catch {}
        }
        try { sessionStorage.setItem(`${sessionStorageKey}-party`, String(activePartyId)); } catch {}
        page.dataset.patientCode = party.customer_code || '';
        page.dataset.patientName = party.name || party.customer_code || '';
        page.dataset.patientDeceased = party.is_deceased ? 'true' : 'false';
        hideSuggestions();
        const currentName = document.querySelector('.dispense-selected-patient');
        const patientDetails = document.querySelector('.dispense-patientbar .dispensing-patient-metadata');
        const patientActions = document.querySelector('.dispense-patientbar .ms-auto');
        const partyMatchesPageCustomer = Boolean(party.customer_code) && party.customer_code === initialPageCustomerCode;
        if (currentName) {
            currentName.replaceChildren();
            if (party.type === 'customer') {
                const initials = `${String(party.first_name || '').slice(0, 1)}${String(party.last_name || '').slice(0, 1)}`.toUpperCase();
                const avatar = document.createElement('span');
                avatar.className = 'patient-avatar';
                avatar.textContent = initials;
                avatar.title = party.customer_code || '';
                const name = document.createElement('span');
                name.className = 'fs-5 fw-semibold';
                name.textContent = party.name || party.customer_code || '';
                currentName.append(avatar, name);
                if (party.is_deceased && party.deceased_display) {
                    const deceased = document.createElement('span');
                    deceased.className = 'text-danger small';
                    deceased.textContent = `(${party.deceased_display})`;
                    currentName.append(deceased);
                    const blocked = document.createElement('span');
                    blocked.className = 'badge text-bg-danger';
                    blocked.textContent = page.dataset.textNoDispensingPossible || '';
                    currentName.append(blocked);
                } else if (party.age_display) {
                    const age = document.createElement('span');
                    age.className = 'text-secondary small';
                    age.textContent = `(${party.age_display})`;
                    currentName.append(age);
                }
            }
        }
        if (patientDetails) {
            patientDetails.replaceChildren();
            patientDetails.classList.toggle('d-none', party.type !== 'customer');
            if (party.type === 'customer') {
                const address = document.createElement('span');
                address.textContent = party.address || page.dataset.textPatientNoAddress || '';
                patientDetails.append(address);
                const mapUrl = party.latitude && party.longitude
                    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${party.latitude},${party.longitude}`)}`
                    : (party.address ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(party.address)}` : '');
                if (mapUrl) {
                    const mapLink = document.createElement('a');
                    mapLink.className = 'address-map-link link-primary text-decoration-none';
                    mapLink.href = mapUrl;
                    mapLink.target = '_blank';
                    mapLink.rel = 'noopener noreferrer';
                    mapLink.title = page.dataset.textGoogleMaps || '';
                    mapLink.setAttribute('aria-label', mapLink.title);
                    mapLink.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0Zm0 1.2a6.8 6.8 0 1 1 0 13.6A6.8 6.8 0 0 1 8 1.2Zm0 1.1a5.7 5.7 0 1 0 0 11.4A5.7 5.7 0 0 0 8 2.3Zm0 1.3a4.4 4.4 0 1 1 0 8.8 4.4 4.4 0 0 1 0-8.8Zm0 2.3a2.1 2.1 0 1 0 0 4.2 2.1 2.1 0 0 0 0-4.2Z"/></svg>';
                    patientDetails.append(mapLink);
                }
                const contactLink = document.createElement('a');
                contactLink.className = 'btn btn-outline-secondary btn-sm contact-manager-trigger';
                contactLink.href = page.dataset.patientEndpoint;
                const contactUrl = new URL(page.dataset.patientEndpoint, window.location.href);
                contactUrl.searchParams.set('patient', party.customer_code);
                contactUrl.searchParams.set('mode', 'patient');
                contactUrl.searchParams.set('dispense_status', dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale');
                contactUrl.searchParams.set('contacts', '1');
                contactUrl.hash = 'contact-manager-modal';
                contactLink.href = contactUrl.href;
                contactLink.title = page.dataset.textContactDetails || '';
                contactLink.setAttribute('aria-label', `${contactLink.title}: ${party.contact_count || 0}`);
                contactLink.textContent = String(party.contact_count || 0);
                patientDetails.append(contactLink);

                const editLink = document.createElement('a');
                editLink.className = 'btn btn-outline-secondary btn-sm patient-edit-trigger';
                const editUrl = new URL(page.dataset.patientEndpoint, window.location.href);
                editUrl.searchParams.set('patient', party.customer_code);
                editUrl.searchParams.set('dispense_status', dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale');
                editUrl.searchParams.set('edit', '1');
                editLink.href = editUrl.href;
                editLink.title = page.dataset.textPatientEdit || '';
                editLink.setAttribute('aria-label', editLink.title);
                editLink.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zM9.793 2.5 1 11.293V15h3.707L13.5 6.207z"/></svg>';
                patientDetails.append(editLink);
            }
        }
        if (patientActions) patientActions.classList.toggle('d-none', !partyMatchesPageCustomer);
        applyDispenseStatus();
        renderPartySelector();
        renderBasket();
        if (promptForPrescriber && party.type === 'customer'
            && dispenseStatusSelect?.selectedOptions[0]?.dataset.requiresCaregiver === 'true'
            && !caregiverByParty.has(String(activePartyId))) {
            openNewPrescriptionModal(true);
        }
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
        try {
            withdrawnToggle.checked = sessionStorage.getItem(withdrawnFilterStorageKey()) === '1';
        } catch {
            withdrawnToggle.checked = false;
        }
        renderSessionReference();
        const session = await sessionRequest('load', {
            customer_code: page.dataset.patientCode || '',
            status_key: dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale',
        });
        try { sessionStorage.setItem(sessionStorageKey, dispensingSessionId); } catch {}
        try {
            const savedCaregivers = JSON.parse(sessionStorage.getItem(prescriptionCaregiversStorageKey()) || '{}');
            for (const [partyId, caregiver] of Object.entries(savedCaregivers)) {
                if (caregiver?.caregiver_code) caregiverByParty.set(String(partyId), caregiver);
            }
        } catch {}
        try {
            const savedPendingCaregiver = JSON.parse(sessionStorage.getItem(pendingCaregiverStorageKey()) || 'null');
            if (savedPendingCaregiver?.caregiver_code) pendingSessionCaregiver = savedPendingCaregiver;
        } catch {}
        dispensingParties = session.parties || [];
        for (const line of session.lines || []) {
            basket.set(String(line.id), line);
            if (line.requires_caregiver && line.caregiver_code && !caregiverByParty.has(String(line.party_id))) {
                setPartyCaregiver(line.party_id, { caregiver_code: line.caregiver_code, name: line.caregiver_name });
            }
        }
        if (session.status_key && Array.from(dispenseStatusSelect?.options || []).some((option) => option.value === session.status_key)) {
            dispenseStatusSelect.value = session.status_key;
            page.dataset.dispenseStatus = session.status_key;
        }
        lastPersistedDispenseStatus = dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'free_sale';
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
            button.classList.toggle('is-keyboard-active', active);
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
        const allProducts = Array.from(basket.values());
        const products = allProducts.filter((product) => Number(product.party_id) === activePartyId);
        count.textContent = String(products.length);
        summary.textContent = t.productCount.replace('{count}', String(products.length));
        if (closeDispensingButton) {
            closeDispensingButton.disabled = allProducts.length === 0 || allProducts.some((product) => product.pending);
        }
        if (!products.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.className = 'empty-basket';
            td.textContent = t.empty;
            tr.append(td);
            rows.append(tr);
            summary.textContent = t.empty;
            return;
        }
        for (const product of products) {
                const tr = document.createElement('tr');
                tr.className = 'dispense-product-line';
                const statusCell = document.createElement('td');
                const statusBadge = document.createElement('span');
                statusBadge.className = 'badge text-bg-light';
                statusBadge.textContent = product.statusShortCode;
                statusCell.append(statusBadge);

                const cnkCell = document.createElement('td');
                const cnk = document.createElement('small');
                cnk.className = 'dispense-line-cnk';
                cnk.textContent = product.cnk;
                cnkCell.append(cnk);

                const quantity = document.createElement('td');
                const quantityInput = document.createElement('input');
                quantityInput.type = 'number';
                quantityInput.min = '1';
                quantityInput.max = '999';
                quantityInput.value = String(product.quantity);
                quantityInput.className = 'form-control form-control-sm basket-qty dispense-line-quantity';
                quantityInput.setAttribute('aria-label', t.quantity);
                quantityInput.disabled = Boolean(product.pending);
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

                const descriptionCell = document.createElement('td');
                descriptionCell.className = 'dispense-product-description';
                const name = document.createElement('span');
                name.className = 'dispense-product-description__name';
                name.textContent = (language === 'fr' ? product.name_fr : product.name_nl)
                    || product.name_nl || product.name_fr || '';
                const packageText = (language === 'fr' ? product.package_fr : product.package_nl)
                    || product.package_nl || product.package_fr || '';
                descriptionCell.append(name);
                if (packageText) {
                    const separator = document.createElement('span');
                    separator.className = 'dispense-product-description__separator';
                    separator.textContent = ' · ';
                    const packageSize = document.createElement('strong');
                    packageSize.className = 'dispense-product-description__package';
                    packageSize.textContent = packageText;
                    descriptionCell.append(separator, packageSize);
                }
                if (product.requires_caregiver) {
                    const caregiverLabel = document.createElement('div');
                    caregiverLabel.className = 'small text-secondary mt-1';
                    caregiverLabel.classList.add('fst-italic');
                    caregiverLabel.textContent = product.caregiver_name || '';
                    descriptionCell.append(caregiverLabel);
                    if (!product.caregiver_code && !product.pending) {
                        const addCaregiver = document.createElement('button');
                        addCaregiver.type = 'button';
                        addCaregiver.className = 'btn btn-sm btn-outline-secondary mt-1';
                        addCaregiver.textContent = t.caregiverAdd || '';
                        addCaregiver.addEventListener('click', () => openLineEdit(product));
                        descriptionCell.append(addCaregiver);
                    }
                }

                const removeCell = document.createElement('td');
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'basket-remove me-2';
                edit.textContent = '✎';
                edit.title = t.lineEdit || '';
                edit.setAttribute('aria-label', t.lineEdit || '');
                edit.disabled = Boolean(product.pending);
                edit.addEventListener('click', () => openLineEdit(product));
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'basket-remove';
                remove.textContent = '×';
                remove.title = t.remove;
                remove.disabled = Boolean(product.pending);
                remove.addEventListener('click', async () => {
                    try {
                        await sessionRequest('line_remove', { line_id: product.id });
                        basket.delete(String(product.id));
                        renderBasket();
                    } catch {
                        showSessionError();
                    }
                });
                removeCell.append(edit, remove);
                tr.append(statusCell, cnkCell, quantity, descriptionCell, removeCell);
                rows.append(tr);
        }
    }

    function openLineEdit(product) {
        if (!lineEditModalElement || !lineEditParty || !lineEditStatus || !lineEditQuantity || !window.bootstrap) return;
        editingLine = product;
        lineEditParty.replaceChildren();
        for (const party of dispensingParties) {
            const option = document.createElement('option');
            option.value = String(party.id);
            option.textContent = party.name;
            option.selected = Number(party.id) === Number(product.party_id);
            lineEditParty.append(option);
        }
        lineEditStatus.value = product.status;
        lineEditQuantity.value = String(product.quantity);
        if (lineCaregiverWrap && lineCaregiverInput && lineCaregiverCode) {
            lineCaregiverWrap.classList.toggle('d-none', !product.requires_caregiver);
            lineCaregiverInput.value = product.caregiver_name || '';
            lineCaregiverCode.value = product.caregiver_code || '';
            if (product.caregiver_code) lineCaregiverInput.dataset.selectedCode = product.caregiver_code;
            else delete lineCaregiverInput.dataset.selectedCode;
            lineCaregiverResults?.replaceChildren();
            lineCaregiverResults?.classList.add('d-none');
        }
        const productName = (language === 'fr' ? product.name_fr : product.name_nl)
            || product.name_nl || product.name_fr || '';
        if (lineEditTitle) lineEditTitle.textContent = `${t.lineEdit || ''} · ${product.cnk} · ${productName}`;
        if (lineEditError) {
            lineEditError.textContent = '';
            lineEditError.classList.add('d-none');
        }
        bootstrap.Modal.getOrCreateInstance(lineEditModalElement).show();
    }

    lineEditStatus?.addEventListener('change', () => {
        const isPrescription = lineEditStatus.selectedOptions[0]?.dataset.requiresCaregiver === 'true';
        lineCaregiverWrap?.classList.toggle('d-none', !isPrescription);
        if (!isPrescription) {
            if (lineCaregiverInput) {
                lineCaregiverInput.value = '';
                delete lineCaregiverInput.dataset.selectedCode;
            }
            if (lineCaregiverCode) lineCaregiverCode.value = '';
        }
    });

    lineEditForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!editingLine || !lineEditQuantity || !lineEditStatus || !lineEditParty || !lineEditSave) return;
        const quantity = Number.parseInt(lineEditQuantity.value, 10);
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 999) {
            lineEditQuantity.focus();
            return;
        }
        lineEditSave.disabled = true;
        try {
            await sessionReady;
            const result = await sessionRequest('line_update', {
                line_id: editingLine.id,
                quantity,
                status_key: lineEditStatus.value,
                party_id: Number(lineEditParty.value),
                caregiver_code: lineCaregiverCode?.value || '',
            });
            if (!result.updated) throw new Error(t.lineEditError || '');
            for (const [key, line] of basket) {
                if (!line.pending) basket.delete(key);
            }
            for (const line of result.lines || []) basket.set(String(line.id), line);
            if (lineEditStatus.selectedOptions[0]?.dataset.requiresCaregiver === 'true' && lineCaregiverCode?.value) {
                setPartyCaregiver(Number(lineEditParty.value), {
                    caregiver_code: lineCaregiverCode.value,
                    name: lineCaregiverInput?.value || '',
                });
            }
            renderBasket();
            editingLine = null;
            bootstrap.Modal.getOrCreateInstance(lineEditModalElement).hide();
        } catch (error) {
            if (lineEditError) {
                lineEditError.textContent = t.lineEditError || error.message || '';
                lineEditError.classList.remove('d-none');
            }
        } finally {
            lineEditSave.disabled = false;
        }
    });

    async function addProduct(product) {
        await sessionReady;
        if (!activePartyId) {
            showSessionError();
            return;
        }
        const status = dispenseStatusSelect?.value || page.dataset.dispenseStatus || 'prescription';
        const pendingId = `pending-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const pendingLine = {
            id: pendingId,
            party_id: activePartyId,
            status,
            requires_caregiver: dispenseStatusSelect?.selectedOptions[0]?.dataset.requiresCaregiver === 'true',
            statusShortCode: dispenseStatusSelect?.selectedOptions[0]?.dataset.shortCode || status,
            cnk: String(product.cnk || ''),
            amp_code: String(product.amp_code || ''),
            ampp_code: String(product.ampp_code || ''),
            name_nl: String(product.prescription_name_nl || product.name_nl || product.official_name || ''),
            name_fr: String(product.prescription_name_fr || product.name_fr || product.official_name || ''),
            package_nl: String(product.pack_display_nl || ''),
            package_fr: String(product.pack_display_fr || ''),
            quantity: 1,
            caregiver_code: dispenseStatusSelect?.selectedOptions[0]?.dataset.requiresCaregiver === 'true'
                ? (caregiverByParty.get(String(activePartyId))?.caregiver_code || '')
                : '',
            caregiver_name: dispenseStatusSelect?.selectedOptions[0]?.dataset.requiresCaregiver === 'true'
                ? (caregiverByParty.get(String(activePartyId))?.name || '')
                : '',
            pending: true,
        };
        basket.set(pendingId, pendingLine);
        renderBasket();
        input.value = '';
        input.focus();
        hideSuggestions();
        try {
            const data = await sessionRequest('line_add', {
                party_id: activePartyId,
                status_key: status,
                caregiver_code: pendingLine.caregiver_code,
                cnk: product.cnk,
                amp_code: product.amp_code,
                ampp_code: product.ampp_code,
            });
            basket.delete(pendingId);
            const savedLineId = String(data.line.id);
            const previousSavedLine = basket.get(savedLineId);
            if (previousSavedLine && Number(previousSavedLine.quantity) > Number(data.line.quantity)) {
                data.line.quantity = previousSavedLine.quantity;
            }
            basket.set(savedLineId, data.line);
            renderBasket();
        } catch {
            basket.delete(pendingId);
            renderBasket();
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
        const patientDeceased = page.dataset.patientDeceased === 'true';
        page.dataset.dispenseStatus = status;
        page.dataset.dispenseMode = freeDispensing ? 'walk_in' : 'patient';
        dispenseStatusSelect.disabled = patientDeceased;
        if (patientStatusInput) patientStatusInput.value = status;
        renderActivePartyLabel();
        if (input) input.disabled = patientDeceased || (!freeDispensing && !page.dataset.patientCode);
        if (patientRequiredMessage) patientRequiredMessage.classList.toggle('d-none', patientDeceased || freeDispensing || Boolean(page.dataset.patientCode));
    }

    dispenseStatusSelect?.addEventListener('change', () => {
        const requestedStatus = dispenseStatusSelect.value;
        void sessionReady.then(() => {
            const requiresCaregiver = dispenseStatusSelect.selectedOptions[0]?.dataset.requiresCaregiver === 'true';
            const activeParty = dispensingParties.find((party) => Number(party.id) === activePartyId);
            const hasKnownCaregiver = activeParty?.type === 'customer'
                ? caregiverByParty.has(String(activePartyId))
                : Boolean(pendingSessionCaregiver?.caregiver_code);
            if (requiresCaregiver && !hasKnownCaregiver) {
                pendingPrescriptionStatusKey = requestedStatus;
                dispenseStatusSelect.value = lastPersistedDispenseStatus;
                applyDispenseStatus();
                renderPartySelector();
                renderBasket();
                openNewPrescriptionModal(true);
                return;
            }
            return persistDispenseStatus(requestedStatus);
        }).catch(showSessionError);
    });
    applyDispenseStatus();

    async function beginReceiptPreview() {
        if (!closeDispensingButton || closeDispensingButton.disabled) return;
        closeDispensingButton.disabled = true;
        try {
            await sessionReady;
            const result = await sessionRequest('receipt_preview');
            if (receiptError) {
                receiptError.classList.add('d-none');
                receiptError.textContent = '';
            }
            const binary = atob(String(result.pdf_base64 || ''));
            const bytes = new Uint8Array(binary.length);
            for (let index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index);
            const blob = new Blob([bytes], { type: 'application/pdf' });
            if (receiptObjectUrl) URL.revokeObjectURL(receiptObjectUrl);
            receiptObjectUrl = URL.createObjectURL(blob);
            if (receiptFrame) receiptFrame.src = receiptObjectUrl;
            if (receiptConfirmButton) receiptConfirmButton.disabled = false;
            if (receiptModalElement && window.bootstrap) bootstrap.Modal.getOrCreateInstance(receiptModalElement).show();
        } catch (error) {
            if (error?.data?.error === 'missing_caregiver' && Array.isArray(error.data.lines)) {
                showMissingCaregiverModal(error.data.lines);
            } else {
                showSessionError();
            }
        } finally {
            renderBasket();
        }
    }

    async function confirmReceipt() {
        if (!receiptConfirmButton) return;
        receiptConfirmButton.disabled = true;
        try {
            await sessionRequest('complete');
            try {
                sessionStorage.removeItem(sessionStorageKey);
                sessionStorage.removeItem(`${sessionStorageKey}-party`);
                sessionStorage.removeItem(`${sessionStorageKey}-page-customer`);
                sessionStorage.removeItem(withdrawnFilterStorageKey());
                sessionStorage.removeItem(prescriptionCaregiversStorageKey());
            } catch {}
            if (receiptModalElement && window.bootstrap) bootstrap.Modal.getOrCreateInstance(receiptModalElement).hide();
            const destination = new URL(window.location.href);
            ['patient', 'q', 'regex', 'include_deceased', 'dispense_status', 'mode',
                'new_patient', 'edit', 'contacts', 'contact_status', 'saved']
                .forEach((key) => destination.searchParams.delete(key));
            destination.hash = '';
            window.location.replace(destination.href);
        } catch (error) {
            if (error?.data?.error === 'missing_caregiver' && Array.isArray(error.data.lines)) {
                if (receiptModalElement && window.bootstrap) bootstrap.Modal.getOrCreateInstance(receiptModalElement).hide();
                showMissingCaregiverModal(error.data.lines);
                receiptConfirmButton.disabled = false;
                return;
            }
            if (receiptError) {
                receiptError.textContent = page.dataset.textSessionError || '';
                receiptError.classList.remove('d-none');
            }
            receiptConfirmButton.disabled = false;
            showSessionError();
        }
    }

    async function discardDispensing() {
        if (!discardConfirmButton || discardConfirmButton.disabled) return;
        discardConfirmButton.disabled = true;
        if (discardError) discardError.classList.add('d-none');
        try {
            await sessionReady;
            await sessionRequest('discard');
            try {
                sessionStorage.removeItem(sessionStorageKey);
                sessionStorage.removeItem(`${sessionStorageKey}-party`);
                sessionStorage.removeItem(`${sessionStorageKey}-page-customer`);
                sessionStorage.removeItem(withdrawnFilterStorageKey());
                sessionStorage.removeItem(prescriptionCaregiversStorageKey());
            } catch {}
            const destination = new URL(window.location.href);
            ['patient', 'q', 'regex', 'include_deceased', 'dispense_status', 'mode',
                'new_patient', 'edit', 'contacts', 'contact_status', 'saved']
                .forEach((key) => destination.searchParams.delete(key));
            destination.hash = '';
            window.location.replace(destination.href);
        } catch {
            if (discardError) {
                discardError.textContent = page.dataset.textSessionError || '';
                discardError.classList.remove('d-none');
            }
            discardConfirmButton.disabled = false;
        }
    }

    function requestDiscardDispensing() {
        if (discardModalElement && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(discardModalElement).show();
        }
    }

    discardConfirmButton?.addEventListener('click', () => { void discardDispensing(); });
    discardModalElement?.addEventListener('shown.bs.modal', () => discardConfirmButton?.focus());

    closeDispensingButton?.addEventListener('click', () => { void beginReceiptPreview(); });
    receiptConfirmButton?.addEventListener('click', () => { void confirmReceipt(); });
    receiptModalElement?.addEventListener('hidden.bs.modal', () => {
        if (receiptFrame) receiptFrame.removeAttribute('src');
        if (receiptObjectUrl) {
            URL.revokeObjectURL(receiptObjectUrl);
            receiptObjectUrl = '';
        }
    });

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
                url.searchParams.set('dispense_status', page.dataset.dispenseStatus || 'free_sale');
                url.searchParams.set('include_revoked', withdrawnToggle?.checked ? '1' : '0');
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
        }, 120);
    });
    withdrawnToggle?.addEventListener('change', () => {
        try {
            if (withdrawnToggle.checked) sessionStorage.setItem(withdrawnFilterStorageKey(), '1');
            else sessionStorage.removeItem(withdrawnFilterStorageKey());
        } catch {}
        hideSuggestions();
        if (input?.value.trim()) input.dispatchEvent(new Event('input', { bubbles: true }));
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
                case 'set_status_free_sale':
                case 'set_status_prescription': {
                    const status = actionKey === 'set_status_free_sale' ? 'free_sale' : 'prescription';
                    if (!dispenseStatusSelect || dispenseStatusSelect.disabled
                        || !Array.from(dispenseStatusSelect.options).some((option) => option.value === status)) {
                        break;
                    }
                    dispenseStatusSelect.value = status;
                    dispenseStatusSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    input?.focus();
                    break;
                }
                case 'close_dispensing':
                    void beginReceiptPreview();
                    break;
                case 'discard_dispensing':
                    requestDiscardDispensing();
                    break;
                case 'open_patient_history':
                    void openPatientHistory();
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
