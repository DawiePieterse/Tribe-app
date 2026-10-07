/*
 * Tribe's small helpers. No inline scripts (the CSP forbids them), so pages mark what they need
 * with data attributes: data-confirm, data-autosubmit, data-kind, data-copy, data-logout.
 */
(() => {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }

    const setOnline = () => document.body.classList.toggle('is-offline', !navigator.onLine);
    window.addEventListener('online', setOnline);
    window.addEventListener('offline', setOnline);

    document.addEventListener('DOMContentLoaded', () => {
        setOnline();

        // Ask before deleting.
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (!window.confirm(form.dataset.confirm)) e.preventDefault();
            });
        });

        document.querySelectorAll('[data-autosubmit]').forEach((el) => {
            el.addEventListener('change', () => el.form && el.form.submit());
        });

        // Event form: show the fields that belong to the chosen kind.
        const kind = document.querySelector('[data-kind]');
        if (kind) {
            const apply = () => {
                document.querySelectorAll('[data-show-for]').forEach((el) => { el.hidden = el.dataset.showFor !== kind.value; });
                document.querySelectorAll('[data-hide-for]').forEach((el) => { el.hidden = el.dataset.hideFor === kind.value; });
            };
            kind.addEventListener('change', apply);
            apply();
        }

        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const input = document.querySelector(button.dataset.copy);
                if (!input) return;
                try {
                    await navigator.clipboard.writeText(input.value);
                } catch {
                    input.select();
                    document.execCommand('copy');
                }
                const label = button.textContent;
                button.textContent = button.dataset.copied;
                setTimeout(() => { button.textContent = label; }, 2000);
            });
        });

        // On logout, forget the pages kept for offline use, so the next person on this device sees nothing.
        document.querySelectorAll('form[data-logout]').forEach((form) => {
            form.addEventListener('submit', () => {
                try { localStorage.removeItem('tribe-queue'); } catch { /* storage may be blocked */ }
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage('logout');
                }
            });
        });
    });
})();
