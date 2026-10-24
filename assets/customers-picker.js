/**
 *
 * @date_cre: Sat 2026-10-24 00:00:00
 * @date_mut: Sat 2026-10-24 00:00:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Searches company customers on demand and opens a selected record.
 *
 */

(() => {
    'use strict';

    const form = document.querySelector('[data-customer-picker]');
    if (!form) return;

    const input = form.querySelector('#customer-search');
    const customerId = form.querySelector('#customer-id');
    const results = form.querySelector('#customer-search-results');
    const status = form.querySelector('#customer-search-status');
    let timer = 0;
    let requestController = null;
    let matches = [];

    function closeResults(message = '') {
        results.replaceChildren();
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        matches = [];
        if (message) status.textContent = message;
    }

    function selectCustomer(customer) {
        customerId.value = String(customer.id);
        input.value = `${customer.name} · ${customer.email}`;
        form.requestSubmit();
    }

    function showResults(customers) {
        results.replaceChildren();
        matches = customers;
        if (customers.length === 0) {
            closeResults(form.dataset.searchEmpty || '');
            return;
        }
        for (const customer of customers) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.setAttribute('role', 'option');
            option.dataset.customerId = String(customer.id);
            const name = document.createElement('strong');
            name.textContent = customer.name;
            const detail = document.createElement('span');
            detail.className = 'd-block small text-secondary';
            detail.textContent = [customer.email, customer.acronym].filter(Boolean).join(' · ');
            option.append(name, detail);
            results.append(option);
        }
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        status.textContent = '';
    }

    async function searchCustomers(query) {
        requestController?.abort();
        requestController = new AbortController();
        try {
            const response = await fetch(`customers.php?ajax=search&q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error('search_unavailable');
            const data = await response.json();
            showResults(Array.isArray(data.results) ? data.results : []);
        } catch (error) {
            if (error.name !== 'AbortError') closeResults(form.dataset.searchUnavailable || '');
        }
    }

    input.addEventListener('input', () => {
        customerId.value = '';
        window.clearTimeout(timer);
        const query = input.value.trim();
        if (query.length < 2) {
            requestController?.abort();
            closeResults();
            return;
        }
        timer = window.setTimeout(() => searchCustomers(query), 200);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && matches.length > 0) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        } else if (event.key === 'Enter' && matches.length === 1) {
            event.preventDefault();
            selectCustomer(matches[0]);
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });

    results.addEventListener('click', (event) => {
        const option = event.target.closest('button[data-customer-id]');
        if (!option) return;
        const customer = matches.find((match) => String(match.id) === option.dataset.customerId);
        if (customer) selectCustomer(customer);
    });

    results.addEventListener('keydown', (event) => {
        const options = [...results.querySelectorAll('button[data-customer-id]')];
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
        if (!event.target.closest('[data-customer-picker]')) closeResults();
    });
})();
