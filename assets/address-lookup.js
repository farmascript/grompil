/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Fri 2026-10-23
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

document.querySelectorAll('[data-address-fields]').forEach((group) => {
    if ((group.dataset.addressCountry || 'BE').toUpperCase() !== 'BE') return;
    const fields = Object.fromEntries([...group.querySelectorAll('[data-address-role]')].map((input) => [input.dataset.addressRole, input]));
    const streetId = group.querySelector('[data-address-street-id]');
    const referenceId = group.querySelector('[data-address-reference-id]');
    const lists = new Map();
    const timers = new Map();
    const requestSequence = new Map();

    const clearReference = () => { if (referenceId) referenceId.value = ''; };
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
            list.className = 'address-suggestions list-group position-absolute w-100 shadow-sm';
            list.setAttribute('role', 'listbox');
            input.parentElement.classList.add('position-relative');
            input.parentElement.append(list);
            list.addEventListener('focusout', (event) => {
                if (event.relatedTarget !== input && !list.contains(event.relatedTarget)) list.replaceChildren();
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
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = item.label;
            button.setAttribute('role', 'option');
            // Keep the input focused until the click selects the suggestion.
            button.addEventListener('mousedown', (event) => {
                if (event.button === 0) event.preventDefault();
            });
            button.addEventListener('click', () => {
                cancelSearch(input);
                onSelect(item);
                list.replaceChildren();
            });
            list.append(button);
        });
        if (!items.length) list.replaceChildren();
    };
    const schedule = (role, type, minimum, params, onSelect) => {
        const input = fields[role];
        if (!input) return;
        input.addEventListener('input', () => {
            cancelSearch(input);
            const sequence = requestSequence.get(input);
            const query = input.value.trim();
            if (query.length < minimum) { ensureList(input).replaceChildren(); return; }
            timers.set(input, setTimeout(async () => {
                try {
                    const items = await search(type, { ...params(), q: query });
                    if (sequence === requestSequence.get(input)) show(input, items, onSelect);
                } catch (error) {
                    if (sequence === requestSequence.get(input)) show(input, [{ label: error.message }], () => {});
                }
            }, 350));
        });
        input.addEventListener('blur', (event) => {
            cancelSearch(input);
            const list = lists.get(input);
            if (!list?.contains(event.relatedTarget)) list?.replaceChildren();
        });
    };

    schedule('postal', 'postal', 2, () => ({}), (item) => {
        fields.postal.value = item.postal_code;
        if (item.city && fields.city) fields.city.value = item.city;
        if (streetId) streetId.value = '';
        clearReference();
    });
    schedule('city', 'municipality', 2, () => ({}), (item) => {
        fields.city.value = item.city;
        if (streetId) streetId.value = '';
        clearReference();
    });
    schedule('street', 'street', 2, () => ({ postal_code: getValue('postal'), city: getValue('city') }), (item) => {
        fields.street.value = item.street;
        if (streetId) streetId.value = item.id;
        clearReference();
    });
    schedule('house', 'address', 1, () => ({
        postal_code: getValue('postal'), city: getValue('city'), street: getValue('street'),
        street_id: streetId?.value ?? '', house_number: getValue('house'),
    }), (item) => {
        fields.street.value = item.street;
        fields.house.value = item.house_number;
        if (fields.box) fields.box.value = item.box_number;
        fields.postal.value = item.postal_code;
        fields.city.value = item.city;
        if (referenceId) referenceId.value = item.id;
    });
});

