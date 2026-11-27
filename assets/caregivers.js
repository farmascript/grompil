/**
 * @date_cre: Fri 2026-11-27 11:20:00
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Shows caregiver address forms and save confirmations.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:assets:caregivers
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-caregiver-address-creator]').forEach((container) => {
        const toggle = container.querySelector('[data-caregiver-address-toggle]');
        const formPanel = container.querySelector('[data-caregiver-address-form]');
        if (!toggle || !formPanel) return;

        toggle.addEventListener('click', () => {
            const isHidden = formPanel.classList.toggle('d-none');
            toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            if (!isHidden) {
                const firstAddressInput = formPanel.querySelector('[data-address-role="postal"]');
                firstAddressInput?.focus();
            }
        });
    });

    const successModal = document.querySelector('[data-caregiver-success-modal]');
    if (successModal && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(successModal).show();
    }
});
