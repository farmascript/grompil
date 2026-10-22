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
 * @description: Handles dialogs in the URL manager.
 *
 */

(() => {
    'use strict';

    const modalElement = document.querySelector('#navigation-manager-modal');
    const frame = modalElement?.querySelector('iframe[data-manager-src]');
    if (!modalElement || !frame) return;

    function focusManager() {
        if (!modalElement.classList.contains('show')) return;
        frame.focus({ preventScroll: true });
        frame.contentWindow?.focus();
    }

    frame.addEventListener('load', focusManager);

    modalElement.addEventListener('shown.bs.modal', focusManager);

    modalElement.addEventListener('show.bs.modal', () => {
        // Reload the manager each time so a regenerated navigation catalog is picked up.
        // Its URL contains a catalog-derived version, so changed catalogs bypass browser cache.
        frame.src = frame.dataset.managerSrc;
        frame.dataset.loaded = 'true';
    });

})();

