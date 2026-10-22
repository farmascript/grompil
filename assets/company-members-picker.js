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
 * @description: Searches and selects companies in membership management.
 *
 */

(() => {
    'use strict';

    const form = document.querySelector('[data-company-members-picker]');
    if (!form) return;

    const input = form.querySelector('#member-company-search');
    const options = [...form.querySelectorAll('#member-company-options option')];
    const companyId = form.querySelector('#member-company-id');
    const openButton = form.querySelector('#member-company-open');

    function syncSelection() {
        const selected = options.find((option) => option.value === input.value);
        companyId.value = selected?.dataset.companyId ?? '';
        openButton.disabled = companyId.value === '';
    }

    input.addEventListener('input', syncSelection);
    input.addEventListener('change', syncSelection);
    form.addEventListener('submit', (event) => {
        syncSelection();
        if (!companyId.value) event.preventDefault();
    });
})();

