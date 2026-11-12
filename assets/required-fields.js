/**
 * @description Adds a visible asterisk to labels associated with required form controls.
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[required], select[required], textarea[required], [aria-required="true"]').forEach((control) => {
        if (control.disabled || !control.labels) {
            return;
        }

        Array.from(control.labels).forEach((label) => {
            if (label.querySelector('.required-field-marker')) {
                return;
            }

            const marker = document.createElement('span');
            marker.className = 'required-field-marker';
            marker.textContent = '*';
            marker.setAttribute('aria-hidden', 'true');
            label.append(marker);
        });
    });
});
