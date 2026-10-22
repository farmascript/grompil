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
 * @description: Warns when the active company context is missing.
 *
 */

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-company-choice]');
    if (!form) {
        return;
    }

    const activeCompanyId = Number.parseInt(form.dataset.activeCompanyId || '0', 10);
    const targetCompanyId = Number.parseInt(form.dataset.targetCompanyId || '0', 10);
    if (activeCompanyId > 0 && targetCompanyId > 0 && activeCompanyId !== targetCompanyId) {
        if (!window.confirm(form.dataset.warning || 'A company switch also affects your other browser tabs. Continue?')) {
            event.preventDefault();
        }
    }
});

