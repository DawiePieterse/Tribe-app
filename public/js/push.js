/*
 * Notifications on this device (My instellings). The button subscribes this device with the push
 * service and tells Tribe, or takes it off again. On an iPhone this only works once Tribe is on the
 * Home Screen and opened from there.
 */
(() => {
    'use strict';

    const box = document.querySelector('[data-push]');
    if (!box) return;

    const button = box.querySelector('[data-push-toggle]');
    const note = box.querySelector('[data-push-note]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const words = JSON.parse(box.dataset.pushWords);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    box.hidden = false;

    const say = (text) => { note.textContent = text; };
    const show = (on) => {
        button.textContent = on ? words.off : words.on;
        button.classList.toggle('quiet', on);
        button.hidden = false;
    };

    if (!supported || (/iPhone|iPad/.test(navigator.userAgent) && !standalone)) {
        button.hidden = true;
        say(words.unsupported);
        return;
    }

    const keyBytes = (base64) => {
        const padded = (base64 + '='.repeat((4 - base64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    };

    const tell = (method, body) => fetch(box.dataset.pushUrl, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    navigator.serviceWorker.ready.then(async (registration) => {
        const current = await registration.pushManager.getSubscription();
        show(!!current);
        if (Notification.permission === 'denied') { button.hidden = true; say(words.denied); }

        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                const existing = await registration.pushManager.getSubscription();
                if (existing) {
                    await tell('DELETE', { endpoint: existing.endpoint });
                    await existing.unsubscribe();
                    show(false);
                    say(words.turnedOff);
                } else {
                    if ((await Notification.requestPermission()) !== 'granted') { say(words.denied); return; }
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: keyBytes(box.dataset.pushKey),
                    });
                    const response = await tell('POST', subscription.toJSON());
                    if (!response.ok) throw new Error(String(response.status));
                    show(true);
                    say(words.turnedOn);
                }
            } catch (e) {
                say(words.failed);
            } finally {
                button.disabled = false;
            }
        });
    });
})();
