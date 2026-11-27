/**
 * @date_cre: Fri 2026-11-27 11:20:00
 * @date_mut: Fri 2026-11-27 12:22:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Shows caregiver address forms and save confirmations.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:assets:caregivers
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.querySelector('[data-caregiver-search]');
    if (searchForm) {
        const input = searchForm.querySelector('[name="q"]');
        const results = searchForm.querySelector('[data-caregiver-search-results]');
        const status = searchForm.querySelector('[data-caregiver-search-status]');
        const minimum = Number.parseInt(searchForm.dataset.searchMinimum || '3', 10);
        let timer = 0;
        let controller = null;
        let matches = [];

        const closeResults = () => {
            results.replaceChildren();
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            matches = [];
        };
        const selectCaregiver = (caregiver) => {
            const url = new URL(searchForm.action, window.location.href);
            url.searchParams.set('code', caregiver.caregiver_code);
            url.searchParams.set('q', input.value.trim());
            window.location.assign(url);
        };
        const showResults = (caregivers) => {
            results.replaceChildren();
            matches = caregivers;
            if (caregivers.length === 0) {
                closeResults();
                status.textContent = searchForm.dataset.searchEmpty || '';
                return;
            }
            for (const caregiver of caregivers) {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action caregiver-search-option';
                option.setAttribute('role', 'option');
                option.dataset.caregiverCode = String(caregiver.caregiver_code);
                const name = document.createElement('strong');
                name.textContent = caregiver.name;
                const type = document.createElement('span');
                type.className = 'badge text-bg-light ms-2';
                type.textContent = caregiver.type;
                const detail = document.createElement('span');
                detail.className = 'd-block small text-secondary';
                detail.textContent = [caregiver.caregiver_code, caregiver.identifiers, caregiver.scope]
                    .filter(Boolean).join(' · ');
                option.append(name, type, detail);
                results.append(option);
            }
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            status.textContent = '';
        };
        const searchCaregivers = async (query) => {
            controller?.abort();
            controller = new AbortController();
            closeResults();
            try {
                const url = new URL(searchForm.action, window.location.href);
                url.searchParams.set('ajax', 'search');
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('search_unavailable');
                const data = await response.json();
                showResults(Array.isArray(data.results) ? data.results : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    closeResults();
                    status.textContent = searchForm.dataset.searchUnavailable || '';
                }
            }
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            controller?.abort();
            const query = input.value.trim();
            closeResults();
            status.textContent = query.length > 0 && query.length < minimum
                ? (searchForm.dataset.searchHint || '')
                : '';
            if (query.length >= minimum) {
                timer = window.setTimeout(() => searchCaregivers(query), 220);
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && matches.length > 0) {
                event.preventDefault();
                results.querySelector('button[role="option"]')?.focus();
            } else if (event.key === 'Enter' && matches.length === 1) {
                event.preventDefault();
                selectCaregiver(matches[0]);
            } else if (event.key === 'Escape') {
                closeResults();
            }
        });
        results.addEventListener('click', (event) => {
            const option = event.target.closest('button[data-caregiver-code]');
            if (!option) return;
            const match = matches.find((caregiver) => String(caregiver.caregiver_code) === option.dataset.caregiverCode);
            if (match) selectCaregiver(match);
        });
        results.addEventListener('keydown', (event) => {
            const options = [...results.querySelectorAll('button[role="option"]')];
            const index = options.indexOf(event.target);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                options[Math.min(index + 1, options.length - 1)]?.focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (index <= 0) input.focus();
                else options[index - 1]?.focus();
            } else if (event.key === 'Escape') {
                closeResults();
                input.focus();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const match = matches.find((caregiver) => String(caregiver.caregiver_code) === event.target.dataset.caregiverCode);
                if (match) selectCaregiver(match);
            }
        });
        document.addEventListener('click', (event) => {
            if (!searchForm.contains(event.target)) closeResults();
        });
        const initialQuery = input.value.trim();
        if (initialQuery.length >= minimum && !new URLSearchParams(window.location.search).has('code')) {
            searchCaregivers(initialQuery);
        } else if (initialQuery.length > 0 && initialQuery.length < minimum) {
            status.textContent = searchForm.dataset.searchHint || '';
        }
    }

    const hasValidRizivCheckDigits = (value) => {
        if (!/^\d{11}$/.test(value)) return false;
        const base = Number.parseInt(value.slice(0, 6), 10);
        const checkDigits = Number.parseInt(value.slice(6, 8), 10);
        return [97, 89, 83, 79].some((modulus) => modulus - (base % modulus) === checkDigits);
    };

    document.querySelectorAll('[data-caregiver-riziv-form]').forEach((form) => {
        const type = form.querySelector('[name="identifier_type_key"]');
        const value = form.querySelector('[name="identifier_value"]');
        if (!type || !value) return;

        const validate = () => {
            const invalid = type.value === 'riziv' && value.value !== '' && !hasValidRizivCheckDigits(value.value.trim());
            value.setCustomValidity(invalid ? (form.dataset.rizivError || '') : '');
            return !invalid;
        };
        type.addEventListener('change', validate);
        value.addEventListener('input', validate);
        form.addEventListener('submit', (event) => {
            if (!validate()) {
                event.preventDefault();
                value.reportValidity();
            }
        });
    });

    document.querySelectorAll('[data-caregiver-add-form-group]').forEach((container) => {
        const toggle = container.querySelector('[data-caregiver-add-form-toggle]');
        const formPanel = container.querySelector('[data-caregiver-add-form]');
        if (!toggle || !formPanel) return;

        toggle.addEventListener('click', () => {
            const isHidden = formPanel.classList.toggle('d-none');
            toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            if (!isHidden) {
                const focusTarget = formPanel.dataset.focusSelector
                    ? formPanel.querySelector(formPanel.dataset.focusSelector)
                    : null;
                const firstControl = formPanel.querySelector('input:not([type="hidden"]), select, textarea');
                (focusTarget || firstControl)?.focus();
            }
        });
    });

    const successModal = document.querySelector('[data-caregiver-success-modal]');
    if (successModal && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(successModal).show();
    }
});
