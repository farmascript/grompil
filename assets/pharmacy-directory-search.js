/**
 *
 * @date_cre: Sun 2026-10-18 20:35:46
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Searches and filters pharmacies in the directory.
 *
 */

(() => {
    'use strict';

    const input = document.querySelector('#pharmacy-search');
    const results = document.querySelector('#pharmacy-search-results');
    if (!input || !results) return;

    let timer = null;
    let controller = null;
    let options = [];

    function closeResults() {
        results.hidden = true;
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        options = [];
    }

    function openPharmacy(permit) {
        const url = new URL('select-company.php', window.location.href);
        url.searchParams.set('fagg_registry', '1');
        url.searchParams.set('pharmacy', permit);
        url.searchParams.set('pharmacy_q', input.value.trim());
        window.location.assign(url);
    }

    function showItems(items) {
        results.replaceChildren();
        options = [];
        for (const item of items) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.setAttribute('role', 'option');
            option.dataset.permit = item.permit;
            const name = document.createElement('strong');
            name.textContent = item.name;
            option.append(name);
            const details = [item.location, item.address].filter(Boolean).join(' · ');
            if (details) {
                const secondary = document.createElement('span');
                secondary.className = 'd-block small text-secondary';
                secondary.textContent = details;
                option.append(secondary);
            }
            results.append(option);
            options.push(option);
        }
        if (options.length === 0) {
            closeResults();
            return;
        }
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    async function search() {
        const query = input.value.trim();
        if (query.length < 2) {
            controller?.abort();
            closeResults();
            return;
        }

        controller?.abort();
        controller = new AbortController();
        try {
            const url = new URL('select-company.php', window.location.href);
            url.searchParams.set('fagg_registry', '1');
            url.searchParams.set('ajax', 'pharmacy_search');
            url.searchParams.set('pharmacy_q', query);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            if (input.value.trim() === query) showItems(data.items || []);
        } catch (error) {
            if (error.name !== 'AbortError') closeResults();
        }
    }

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(search, 180);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && options.length > 0) {
            event.preventDefault();
            options[0].focus();
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });
    results.addEventListener('click', (event) => {
        const option = event.target.closest('button[data-permit]');
        if (option) openPharmacy(option.dataset.permit);
    });
    results.addEventListener('keydown', (event) => {
        const index = options.indexOf(event.target);
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            options[Math.min(index + 1, options.length - 1)]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (index <= 0) input.focus();
            else options[index - 1].focus();
        } else if (event.key === 'Escape') {
            closeResults();
            input.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('#pharmacy-search, #pharmacy-search-results')) closeResults();
    });
})();

