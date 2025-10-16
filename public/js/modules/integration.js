import { showToast } from '/js/app.js';

export function initIntegrationForm(selector) {
    const form = document.querySelector(selector);
    if (!form) {
        return;
    }
    const groups = form.querySelectorAll('.integration-group');
    const radios = form.querySelectorAll('input[name="integration_mode"]');

    const toggleGroups = () => {
        const mode = form.querySelector('input[name="integration_mode"]:checked')?.value || 'webhook';
        groups.forEach((group) => {
            const active = group.getAttribute('data-integration') === mode;
            group.toggleAttribute('hidden', !active);
            group.querySelectorAll('input, textarea').forEach((field) => {
                if (active) {
                    field.removeAttribute('disabled');
                } else {
                    field.setAttribute('disabled', 'disabled');
                }
            });
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', toggleGroups));
    toggleGroups();

    form.addEventListener('reset', () => {
        setTimeout(toggleGroups, 100);
    });

    window.addEventListener('integration:test:success', () => {
        showToast('Mensagem de teste enviada.', 'success');
    });
}
