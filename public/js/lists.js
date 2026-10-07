/*
 * Lists that keep working in a shop with no signal. Ticks and new items are shown at once and sent
 * to the server; when that fails because the phone is offline, they wait in a queue in localStorage
 * and are sent when the signal comes back. Without JavaScript the forms still post normally.
 */
(() => {
    'use strict';

    const KEY = 'tribe-queue';
    const root = document.querySelector('[data-list]');
    if (!root) return;

    const token = document.querySelector('meta[name="csrf-token"]').content;
    const pendingText = root.dataset.pendingText;

    const load = () => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; } };
    const save = (queue) => { try { localStorage.setItem(KEY, JSON.stringify(queue)); } catch { /* full or blocked */ } };

    const send = (op) => fetch(op.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(op.body),
    }).then((response) => {
        if (response.status === 419 || response.status === 401) {
            // Session or token expired: keep the queue and reload to get a fresh page and token.
            throw new Error('reload');
        }
        // 4xx other than those means the item is gone or invalid; drop the operation.
        return response;
    });

    let flushing = false;
    const flush = async () => {
        if (flushing || !navigator.onLine) return;
        flushing = true;
        let queue = load();
        let sentAny = false;
        while (queue.length) {
            try {
                await send(queue[0]);
            } catch (error) {
                flushing = false;
                if (error.message === 'reload') window.location.reload();
                return;
            }
            queue.shift();
            save(queue);
            sentAny = true;
        }
        flushing = false;
        if (sentAny) window.location.reload();
    };

    const enqueueOrSend = (op, onDone) => {
        send(op).then(onDone).catch((error) => {
            if (error.message === 'reload') { window.location.reload(); return; }
            const queue = load();
            queue.push(op);
            save(queue);
        });
    };

    // Ticking an item.
    root.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.matches('[data-toggle-form]')) return;
        e.preventDefault();

        const item = form.closest('[data-item]');
        const done = !item.classList.contains('is-done');
        item.classList.toggle('is-done', done);
        form.querySelector('.tick').setAttribute('aria-pressed', String(done));
        form.querySelector('input[name="done"]').value = done ? '0' : '1';

        enqueueOrSend({ url: item.dataset.toggleUrl, body: { done } });
    });

    // Adding an item.
    const addForm = root.querySelector('[data-add-form]');
    addForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(addForm));
        delete data._token;
        if (!data.title || !data.title.trim()) return;

        const li = document.createElement('li');
        li.className = 'item is-pending';
        li.dataset.pending = pendingText;
        const title = document.createElement('span');
        title.className = 'title';
        title.dataset.pending = pendingText;
        const text = document.createElement('span');
        text.textContent = data.title.trim();
        title.appendChild(text);
        li.appendChild(title);
        root.querySelector('[data-open]').prepend(li);
        const empty = root.querySelector('[data-empty]');
        if (empty) empty.remove();

        addForm.reset();
        addForm.querySelector('input[name="title"]').focus();

        // Once saved, the server's version of the list (with working buttons) replaces the placeholder.
        enqueueOrSend({ url: root.dataset.addUrl, body: data }, () => window.location.reload());
    });

    window.addEventListener('online', flush);
    flush();
})();
