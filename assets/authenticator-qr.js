/**
 * @date_cre: Sun 2026-11-08 12:03:58
 * @date_mut: Sun 2026-11-08 12:08:20
 * @description: Renders the platform TOTP provisioning URI as a local QR code.
 */

(() => {
    const wrapper = document.querySelector('[data-authenticator-qr-wrapper]');
    const qrContainer = wrapper?.querySelector('[data-authenticator-qr]');
    if (!wrapper || !qrContainer || typeof qrcode !== 'function') {
        return;
    }

    const provisioningUri = qrContainer.dataset.otpauthUri || '';
    if (!provisioningUri) {
        return;
    }

    try {
        const qr = qrcode(0, 'M');
        qr.addData(provisioningUri, 'Byte');
        qr.make();
        const label = qrContainer.dataset.qrLabel || 'Authenticator setup QR code';
        qrContainer.innerHTML = qr.createSvgTag(5, 4, label, label);
        wrapper.classList.remove('d-none');
    } catch (error) {
        console.error('Could not render authenticator QR code.', error);
        wrapper.classList.add('d-none');
    }
})();
