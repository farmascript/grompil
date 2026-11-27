/**
 * @date_cre: Fri 2026-11-27 11:20:00
 * @date_mut: Fri 2026-11-27 12:05:00
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
    const hasValidRizivCheckDigits = (value) => {
        if (!/^\d{11}$/.test(value)) return false;
        const base = Number.parseInt(value.slice(0, 6), 10);
        const checkDigits = Number.parseInt(value.slice(6, 8), 10);
        return [97, 89, 83, 79].some((modulus) => modulus - (base % modulus) === checkDigits);
    };

    document.querySelectorAll('[data-caregiver-riziv-form]').forEach((form) => {
        const type = form.querySelector('[name="identifier_type_key"]');
        const value = form.querySelector('[name="identifier_value"]');
        if (!type || !value) return;

        const validate = () => {
            const invalid = type.value === 'riziv' && value.value !== '' && !hasValidRizivCheckDigits(value.value.trim());
            value.setCustomValidity(invalid ? (form.dataset.rizivError || '') : '');
            return !invalid;
        };
        type.addEventListener('change', validate);
        value.addEventListener('input', validate);
        form.addEventListener('submit', (event) => {
            if (!validate()) {
                event.preventDefault();
                value.reportValidity();
            }
        });
    });

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
