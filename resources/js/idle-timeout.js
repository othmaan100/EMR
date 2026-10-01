/**
 * Idle sign-out warning.
 *
 * The server signs the user out after N minutes without a request. While the
 * user is actually working (typing, clicking) we send a light keep-alive ping
 * at most every few minutes, so long notes are never lost. When nobody has
 * touched the page, a warning appears one minute before sign-out.
 */
export function initIdleTimeout() {
    const minutes = Number(document.querySelector('meta[name="idle-minutes"]')?.content);
    const pingUrl = document.querySelector('meta[name="ping-url"]')?.content;
    const loginUrl = document.querySelector('meta[name="login-url"]')?.content;
    const modalEl = document.getElementById('idleModal');
    if (!minutes || !pingUrl || !modalEl) return;

    const limit = minutes * 60_000;
    const pingEvery = Math.min(5 * 60_000, limit / 3);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const modal = new window.bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    const countdown = modalEl.querySelector('[data-countdown]');
    let lastServer = Date.now();
    let warning = false;

    const ping = async () => {
        lastServer = Date.now();
        try {
            const res = await fetch(pingUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            if (res.status === 401 || res.status === 419) window.location = loginUrl;
        } catch {
            /* offline: the next check will retry */
        }
    };

    const onActivity = () => {
        if (!warning && Date.now() - lastServer > pingEvery) ping();
    };
    ['keydown', 'mousedown', 'touchstart', 'scroll'].forEach((e) => document.addEventListener(e, onActivity, { passive: true }));

    modalEl.querySelector('[data-stay]').addEventListener('click', () => {
        warning = false;
        modal.hide();
        ping();
    });

    setInterval(() => {
        const idle = Date.now() - lastServer;
        if (idle >= limit) {
            window.location = loginUrl;
        } else if (idle >= limit - 60_000) {
            if (!warning) {
                warning = true;
                modal.show();
            }
            countdown.textContent = Math.max(0, Math.ceil((limit - idle) / 1000));
        }
    }, 1000);
}
