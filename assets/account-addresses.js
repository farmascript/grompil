/**
 * @date_cre: Sat 2026-11-07 16:06:44
 * @date_mut: Sat 2026-11-07 16:06:44
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Opens the account address manager after add/edit requests.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:assets:account-addresses
 */

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('account-address-modal');
    if (!modal || modal.dataset.autoOpen !== 'true' || !window.bootstrap?.Modal) {
        return;
    }

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
});
