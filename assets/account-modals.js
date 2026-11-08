/**
 * @date_cre: Sun 2026-11-08 13:36:00
 * @date_mut: Sun 2026-11-08 13:48:27
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Opens account action dialogs after server-side validation errors or multi-step setup actions.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:assets:account-modals
 */

document.addEventListener('DOMContentLoaded', () => {
    if (!window.bootstrap?.Modal) {
        return;
    }

    document.querySelectorAll('[data-account-modal-open="true"]').forEach((modal) => {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });
});
