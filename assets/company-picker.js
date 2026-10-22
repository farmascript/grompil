/**
 *
 * @date_cre: Sun 2026-10-18 20:35:48
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Searches and selects a company.
 *
 */

(() => {
    'use strict';

    const input = document.querySelector('#company-search');
    const results = document.querySelector('#company-search-results');
    const rows = [...document.querySelectorAll('#companies-table tbody tr[data-company-id]')];
    if (!input || !results || rows.length === 0) return;

    let matches = [];

    function closeResults() {
        results.hidden = true;
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        matches = [];
    }

    function chooseCompany(companyId) {
        window.location.href = `companies.php?edit=${encodeURIComponent(companyId)}`;
    }

    function updateResults() {
        const query = input.value.trim().toLocaleLowerCase();
        results.replaceChildren();
        matches = [];

        if (query.length < 2) {
            closeResults();
            return;
        }

        matches = rows.filter((row) => row.dataset.companySearch.toLocaleLowerCase().includes(query));
        if (matches.length === 0) {
            closeResults();
            return;
        }

        for (const row of matches.slice(0, 10)) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action';
            option.setAttribute('role', 'option');
            option.dataset.companyId = row.dataset.companyId;

            const name = document.createElement('strong');
            name.textContent = row.dataset.companyName;
            option.append(name);

            const location = row.cells[1]?.textContent.trim();
            const slug = row.querySelector('td:first-child .small')?.textContent.trim();
            const detail = [location, slug].filter(Boolean).join(' · ');
            if (detail) {
                const secondary = document.createElement('span');
                secondary.className = 'd-block small text-secondary';
                secondary.textContent = detail;
                option.append(secondary);
            }
            results.append(option);
        }

        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    input.addEventListener('input', updateResults);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && matches.length > 0) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        } else if (event.key === 'Enter' && matches.length === 1) {
            event.preventDefault();
            chooseCompany(matches[0].dataset.companyId);
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });

    results.addEventListener('click', (event) => {
        const option = event.target.closest('button[data-company-id]');
        if (option) chooseCompany(option.dataset.companyId);
    });
    results.addEventListener('keydown', (event) => {
        const options = [...results.querySelectorAll('button[data-company-id]')];
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
        if (!event.target.closest('[data-company-picker]')) closeResults();
    });
})();

