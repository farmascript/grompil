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
 * @description: Handles selection of the active company context.
 *
 */

(() => {
    'use strict';

    const input = document.querySelector('#company-picker-search');
    const results = document.querySelector('#company-picker-results');
    const forms = [...document.querySelectorAll('[data-company-choice][data-company-search]')];
    if (!input || !results || forms.length < 2) return;

    let matches = [];

    function normalize(value) {
        return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    }

    function closeResults() {
        results.hidden = true;
        results.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        matches = [];
    }

    function chooseCompany(id) {
        const form = forms.find((item) => item.dataset.companyId === id);
        form?.requestSubmit();
    }

    function updateResults() {
        const query = normalize(input.value.trim());
        closeResults();
        for (const form of forms) form.hidden = query !== '';
        if (query === '') return;

        matches = forms.filter((form) => normalize(form.dataset.companySearch).includes(query));
        if (matches.length === 0) {
            results.textContent = input.dataset.noResults || 'Geen resultaten';
            results.hidden = false;
            return;
        }

        for (const form of matches.slice(0, 10)) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'btn btn-link d-block text-start p-0 company-picker-option';
            option.setAttribute('role', 'option');
            option.dataset.companyId = form.dataset.companyId;
            option.textContent = form.dataset.companyName;
            const slug = form.querySelector('.text-secondary')?.textContent.trim();
            if (slug) option.append(document.createTextNode(` · ${slug}`));
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

