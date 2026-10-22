/**
 *
 * @date_cre: Sun 2026-10-18 20:35:47
 * @date_mut: Tue 2026-10-20 21:21:08
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Handles language selection on the public site.
 *
 */

const languageScrollTargetKey = 'grompil-language-scroll-target';
const languageScrollYKey = 'grompil-language-scroll-y';

try {
    const target = sessionStorage.getItem(languageScrollTargetKey);
    const savedY = Number.parseInt(sessionStorage.getItem(languageScrollYKey) ?? '', 10);
    if (target === window.location.href && Number.isFinite(savedY)) {
        sessionStorage.removeItem(languageScrollTargetKey);
        sessionStorage.removeItem(languageScrollYKey);
        window.addEventListener('load', () => {
            requestAnimationFrame(() => window.scrollTo(0, savedY));
        }, { once: true });
    }
} catch {
    // Language switching still works if session storage is unavailable.
}

document.querySelectorAll('[data-language-parameter]').forEach((selector) => {
    selector.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set(selector.dataset.languageParameter, selector.value);
        try {
            sessionStorage.setItem(languageScrollTargetKey, url.toString());
            sessionStorage.setItem(languageScrollYKey, String(window.scrollY));
        } catch {
            // Language switching still works if session storage is unavailable.
        }
        window.location.assign(url.toString());
    });
});

