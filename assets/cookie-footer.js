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
 * @description: Remembers the user choice in the cookie notice.
 *
 */

(() => {
    const footer = document.querySelector('[data-cookie-footer]');
    if (!footer) {
        return;
    }

    const dismissedKey = 'grompil-cookie-footer-dismissed-v2';
    const dismissalLifetime = 180 * 24 * 60 * 60 * 1000;
    const dismissButton = footer.querySelector('[data-cookie-footer-dismiss]');

    const dismiss = () => {
        footer.hidden = true;
        footer.classList.add('d-none');
        try {
            localStorage.setItem(dismissedKey, String(Date.now() + dismissalLifetime));
        } catch {
            // The footer remains dismissed for the current page.
        }
    };

    dismissButton?.addEventListener('click', dismiss);

    try {
        const dismissedUntil = Number.parseInt(localStorage.getItem(dismissedKey) ?? '', 10);
        if (Number.isFinite(dismissedUntil) && dismissedUntil > Date.now()) {
            footer.hidden = true;
            footer.classList.add('d-none');
        } else {
            localStorage.removeItem(dismissedKey);
        }
    } catch {
        // Dismissal still works for the current page when browser storage is blocked.
    }
})();

