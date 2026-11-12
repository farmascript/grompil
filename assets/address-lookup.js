/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Thu 2026-11-12
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Adds address suggestions to address forms.
 *
 */

let addressSuggestionListId = 0;
let addressSuggestionOptionId = 0;

document.querySelectorAll('[data-address-fields]').forEach((group) => {
    if ((group.dataset.addressCountry || 'BE').toUpperCase() !== 'BE') return;
    const fields = Object.fromEntries([...group.querySelectorAll('[data-address-role]')].map((input) => [input.dataset.addressRole, input]));
    const streetId = group.querySelector('[data-address-street-id]');
    const referenceId = group.querySelector('[data-address-reference-id]');
    const coordinateFields = Object.fromEntries([...group.querySelectorAll('[data-address-coordinate]')]
        .map((input) => [input.dataset.addressCoordinate, input]));
    const municipalityNis = group.querySelector('[data-address-nis-code]');
    const status = group.querySelector('[data-address-status]');
    const manualWrap = group.querySelector('[data-address-manual-wrap]');
    const manualConfirm = group.querySelector('[data-address-manual-confirm]');
    const lists = new Map();
    const timers = new Map();
    const requestSequence = new Map();

    const clearReference = () => {
        if (referenceId) referenceId.value = '';
        Object.values(coordinateFields).forEach((input) => { input.value = ''; });
        if (manualConfirm) manualConfirm.checked = false;
        manualWrap?.classList.add('d-none');
    };
    const setStatus = (message = '') => {
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('d-none', message === '');
    };
    const clearStreetAndHouse = () => {
        ['street', 'house', 'box'].forEach((role) => {
            if (fields[role]) fields[role].value = '';
        });
        if (streetId) streetId.value = '';
        clearReference();
    };
    Object.values(fields).forEach((input) => input.addEventListener('input', clearReference));

    const getValue = (role) => fields[role]?.value.trim() ?? '';
    const cancelSearch = (input) => {
        clearTimeout(timers.get(input));
        requestSequence.set(input, (requestSequence.get(input) ?? 0) + 1);
    };
    const ensureList = (input) => {
        let list = lists.get(input);
        if (!list) {
            list = document.createElement('div');
            list.className = 'address-suggestions list-group shadow-sm';
            list.setAttribute('role', 'listbox');
            list.id = `address-suggestions-${++addressSuggestionListId}`;
            list.tabIndex = -1;
            document.body.append(list);
            const positionList = () => {
                if (list.hidden || !list.childElementCount) return;
                const rect = input.getBoundingClientRect();
                const containerRect = group.getBoundingClientRect();
                const gap = 4;
                const below = Math.max(0, window.innerHeight - rect.bottom - gap - 8);
                const above = Math.max(0, rect.top - gap - 8);
                const placeBelow = below >= 160 || below >= above;
                const available = placeBelow ? below : above;
                const maxHeight = Math.max(0, Math.min(288, window.innerHeight * 0.45, available));
                const width = Math.min(containerRect.width, window.innerWidth - 16);
                list.style.left = `${Math.max(8, Math.min(containerRect.left, window.innerWidth - width - 8))}px`;
                list.style.width = `${width}px`;
                list.style.maxHeight = `${maxHeight}px`;
                list.style.height = `${Math.min(list.scrollHeight, maxHeight)}px`;
                list.style.top = placeBelow
                    ? `${rect.bottom + gap}px`
                    : `${Math.max(8, rect.top - list.offsetHeight - gap)}px`;
            };
            list.updatePosition = positionList;
            window.addEventListener('resize', positionList);
            window.addEventListener('scroll', positionList, true);
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-haspopup', 'listbox');
            input.setAttribute('aria-controls', list.id);
            input.setAttribute('aria-expanded', 'false');
            list.addEventListener('pointerdown', () => { list.dataset.pointerDown = 'true'; });
            const finishPointer = () => {
                if (list.dataset.pointerDown !== 'true') return;
                delete list.dataset.pointerDown;
                input.focus({ preventScroll: true });
            };
            window.addEventListener('pointerup', finishPointer);
            window.addEventListener('pointercancel', finishPointer);
            list.addEventListener('focusout', (event) => {
                if (event.relatedTarget !== input && !list.contains(event.relatedTarget)) list.replaceChildren();
            });
            list.addEventListener('scroll', (event) => event.stopPropagation(), { passive: true });
            input.addEventListener('keydown', (event) => {
                const options = [...list.querySelectorAll('[role="option"]')];
                if (!options.length) return;
                const activeIndex = options.findIndex((option) => option.getAttribute('aria-selected') === 'true');
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const direction = event.key === 'ArrowDown' ? 1 : -1;
                    const nextIndex = activeIndex < 0
                        ? (direction > 0 ? 0 : options.length - 1)
                        : (activeIndex + direction + options.length) % options.length;
                    options.forEach((option, index) => {
                        const selected = index === nextIndex;
                        option.setAttribute('aria-selected', selected ? 'true' : 'false');
                        option.classList.toggle('active', selected);
                    });
                    input.setAttribute('aria-activedescendant', options[nextIndex].id);
                    options[nextIndex].scrollIntoView({ block: 'nearest' });
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    (activeIndex >= 0 ? options[activeIndex] : options[0]).click();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    list.replaceChildren();
                    input.setAttribute('aria-expanded', 'false');
                    input.removeAttribute('aria-activedescendant');
                }
            });
            lists.set(input, list);
        }
        return list;
    };
    const search = async (type, params) => {
        const url = new URL('address_api.php', document.baseURI);
        url.searchParams.set('type', type);
        Object.entries(params).forEach(([key, value]) => { if (value) url.searchParams.set(key, value); });
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Adres zoeken is tijdelijk niet beschikbaar.');
        return data.items || [];
    };
    const show = (input, items, onSelect) => {
        const list = ensureList(input);
        list.replaceChildren();
        input.removeAttribute('aria-activedescendant');
        input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = item.label;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', 'false');
            button.tabIndex = -1;
            button.id = `address-suggestion-option-${++addressSuggestionOptionId}`;
            // Keep the input focused until the click selects the suggestion.
            button.addEventListener('mousedown', (event) => {
                if (event.button === 0) event.preventDefault();
            });
            button.addEventListener('click', () => {
                cancelSearch(input);
                setStatus('');
                onSelect(item);
                list.replaceChildren();
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
            });
            list.append(button);
        });
        if (!items.length) list.replaceChildren();
        if (items.length) requestAnimationFrame(() => list.updatePosition?.());
    };
    const schedule = (role, type, minimum, params, onSelect, onResults = null) => {
        const input = fields[role];
        if (!input) return;
        input.addEventListener('input', () => {
            cancelSearch(input);
            const sequence = requestSequence.get(input);
            const query = input.value.trim();
            setStatus('');
            if (query.length < minimum) {
                ensureList(input).replaceChildren();
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                return;
            }
            timers.set(input, setTimeout(async () => {
                try {
                    const items = await search(type, { ...params(), q: query });
                    if (sequence === requestSequence.get(input)) {
                        if (onResults) onResults(items, query);
                        show(input, items, onSelect);
                        if (!items.length && input.value.trim() === query && status) {
                            setStatus(status.dataset.noResults || '');
                        }
                    }
                } catch (error) {
                    if (sequence === requestSequence.get(input)) {
                        ensureList(input).replaceChildren();
                        setStatus(error.message);
                    }
                }
            }, 350));
        });
        input.addEventListener('blur', (event) => {
            cancelSearch(input);
            const list = lists.get(input);
            if (!list?.contains(event.relatedTarget)) {
                setTimeout(() => {
                    if (list?.dataset.pointerDown === 'true' || document.activeElement === input
                        || list?.contains(document.activeElement)) return;
                    list?.replaceChildren();
                    input.setAttribute('aria-expanded', 'false');
                    input.removeAttribute('aria-activedescendant');
                }, 0);
            }
        });
    };

    if (fields.postal && fields.city) {
        fields.postal.addEventListener('input', () => {
            fields.city.value = '';
            if (municipalityNis) municipalityNis.value = '';
            clearStreetAndHouse();
        });
        fields.city.addEventListener('input', () => {
            fields.postal.value = '';
            if (municipalityNis) municipalityNis.value = '';
            clearStreetAndHouse();
        });
    }
    schedule('postal', 'postal', 2, () => ({}), (item) => {
        fields.postal.value = item.postal_code;
        if (item.city && fields.city) fields.city.value = item.city;
        if (municipalityNis) municipalityNis.value = '';
        clearStreetAndHouse();
    }, (items, query) => {
        if (!fields.city || fields.postal.value.trim() !== query || fields.city.value.trim() !== '') return;
        const cities = [...new Set(items.map((item) => item.city?.trim()).filter(Boolean))];
        if (cities.length === 1) fields.city.value = cities[0];
    });
    schedule('city', 'locality', 2, () => ({}), (item) => {
        fields.city.value = item.city;
        const selectedPostalCode = item.postal_code || item.postCode || item.postalCode || '';
        if (fields.postal) fields.postal.value = selectedPostalCode;
        if (municipalityNis) municipalityNis.value = item.nis_code || '';
        if (fields.postal) {
            cancelSearch(fields.postal);
            lists.get(fields.postal)?.replaceChildren();
        }
        clearStreetAndHouse();
        if (!selectedPostalCode) setStatus(status?.dataset.postalReminder || '');
    });
    schedule('street', 'street', 2, () => ({
        postal_code: getValue('postal'),
        nis_code: getValue('postal') ? '' : (municipalityNis?.value ?? ''),
    }), (item) => {
        fields.street.value = item.street;
        if (item.city && fields.city) fields.city.value = item.city;
        if (streetId) streetId.value = item.id;
        clearReference();
        if (getValue('house')) {
            fields.house.dispatchEvent(new Event('input', { bubbles: true }));
        } else if (!getValue('postal')) {
            setStatus(status?.dataset.postalReminder || '');
        }
    });
    const selectOfficialAddress = (item) => {
        fields.street.value = item.street;
        fields.house.value = item.house_number;
        if (fields.box) fields.box.value = item.box_number;
        fields.postal.value = item.postal_code;
        fields.city.value = item.city;
        if (referenceId) referenceId.value = item.id;
        Object.entries(coordinateFields).forEach(([key, input]) => { input.value = item[key] || ''; });
        if (manualConfirm) manualConfirm.checked = false;
        manualWrap?.classList.add('d-none');
        setStatus('');
    };
    schedule('house', 'address', 1, () => ({
        postal_code: getValue('postal'), city: getValue('postal') ? '' : getValue('city'), street: getValue('street'),
        nis_code: getValue('postal') ? '' : (municipalityNis?.value ?? ''),
        street_id: streetId?.value ?? '', house_number: getValue('house'),
    }), (item) => {
        selectOfficialAddress(item);
        if (group.dataset.addressAutosave === '1') {
            const addressForm = group.closest('form');
            if (addressForm) requestAnimationFrame(() => addressForm.requestSubmit());
        }
    }, (items) => {
        const postalCodes = [...new Set(items.map((item) => item.postal_code?.trim()).filter(Boolean))];
        if (postalCodes.length === 1 && fields.postal && !getValue('postal')) {
            fields.postal.value = postalCodes[0];
            if (items[0].city && fields.city) fields.city.value = items[0].city;
            if (status) setStatus('');
        }
    });

    if (manualConfirm) {
        manualConfirm.addEventListener('change', () => {
            if (manualConfirm.checked) setStatus('');
        });
    }

    const form = group.closest('form');
    let checkingAddressBeforeSubmit = false;
    form?.addEventListener('submit', async (event) => {
        const hasAddress = ['street', 'house', 'postal', 'city'].some((role) => getValue(role) !== '');
        if (!hasAddress || (referenceId?.value.trim() ?? '') !== '' || manualConfirm?.checked) return;

        event.preventDefault();
        if (checkingAddressBeforeSubmit) return;
        checkingAddressBeforeSubmit = true;
        cancelSearch(fields.house);
        const query = {
            postal_code: getValue('postal'), city: getValue('postal') ? '' : getValue('city'), street: getValue('street'),
            nis_code: getValue('postal') ? '' : (municipalityNis?.value ?? ''),
            street_id: streetId?.value ?? '', house_number: getValue('house'),
        };
        try {
            if (getValue('house') && getValue('street') && (getValue('postal') || getValue('city'))) {
                const items = await search('address', query);
                if (items.length) {
                    setStatus(status?.dataset.selectRequired || '');
                    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
                    show(fields.house, items, (item) => {
                        selectOfficialAddress(item);
                        if (submitter?.isConnected) form.requestSubmit(submitter);
                        else form.requestSubmit();
                    });
                    fields.house.focus();
                    return;
                }
            }
            manualWrap?.classList.remove('d-none');
            setStatus(status?.dataset.manualPrompt || status?.dataset.manualRequired || '');
            manualConfirm?.focus();
        } catch (error) {
            manualWrap?.classList.remove('d-none');
            setStatus(status?.dataset.manualPrompt || status?.dataset.manualRequired || error.message);
            manualConfirm?.focus();
        } finally {
            checkingAddressBeforeSubmit = false;
        }
    });
});

