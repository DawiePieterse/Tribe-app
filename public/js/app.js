/*
 * Tribe's small helpers. No inline scripts (the CSP forbids them), so pages mark what they need
 * with data attributes: data-confirm, data-autosubmit, data-kind, data-copy, data-share, data-shrink, data-logout.
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

        // Share a shopping list through the iPhone share sheet (to Sixty60, WhatsApp, Notes); else copy it.
        document.querySelectorAll('[data-share]').forEach((button) => {
            button.addEventListener('click', async () => {
                const text = button.dataset.share;
                if (navigator.share) {
                    try { await navigator.share({ text }); return; } catch (e) { if (e && e.name === 'AbortError') return; }
                }
                try {
                    await navigator.clipboard.writeText(text);
                    const label = button.innerHTML;
                    button.textContent = button.dataset.shared;
                    setTimeout(() => { button.innerHTML = label; }, 2000);
                } catch { /* nothing more to try */ }
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
