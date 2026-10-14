/*
 * Tribe's small helpers. No inline scripts (the CSP forbids them), so pages mark what they need
 * with data attributes: data-confirm, data-autosubmit, data-kind, data-copy, data-shrink, data-logout.
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

        // Copy a field's text and say so on the button for a moment (data-copied holds the words).
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

        // Make big phone photos smaller before they are sent (a till slip reads fine at 1600 px).
        document.querySelectorAll('input[type=file][data-shrink]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file || !file.type.startsWith('image/') || file.size < 1500000 || typeof DataTransfer === 'undefined') return;
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => {
                    const scale = Math.min(1, 1600 / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    URL.revokeObjectURL(url);
                    canvas.toBlob((blob) => {
                        if (!blob) return;
                        const list = new DataTransfer();
                        list.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                        input.files = list.files;
                    }, 'image/jpeg', 0.85);
                };
                img.onerror = () => URL.revokeObjectURL(url);
                img.src = url;
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

// "+ Nog ’n datum": copies the last empty date row.
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-add-row]');
    if (!button) return;
    const rows = button.closest('fieldset').querySelector('[data-rows]');
    const last = rows.querySelector('[data-row]:last-child');
    if (!last || rows.children.length >= 12) return;
    const row = last.cloneNode(true);
    row.querySelectorAll('input').forEach((input) => { input.value = ''; });
    rows.appendChild(row);
    row.querySelector('input').focus();
});

// A tap on a link to another Tribe screen: after a moment, a thin bar at the top shows the next screen
// is on its way (app.css, html.is-leaving). Cleared when a page is shown again from the back button.
document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const link = e.target.closest('a[href]');
    if (!link || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
    if (/\.ics$/.test(url.pathname)) return;
    setTimeout(() => document.documentElement.classList.add('is-leaving'), 150);
});
document.addEventListener('submit', (e) => {
    if (!e.defaultPrevented && !e.target.target) setTimeout(() => document.documentElement.classList.add('is-leaving'), 150);
});
window.addEventListener('pageshow', () => document.documentElement.classList.remove('is-leaving'));
