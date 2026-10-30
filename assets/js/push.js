/* Academics web push subscription helper */
(function () {
    'use strict';
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return;
    }
    const base = (window.ACADEMICS_BASE || '/');
    const vapidKey = window.ACADEMICS_VAPID_PUBLIC_KEY;
    if (!vapidKey) { return; }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function getCsrfToken() {
        const m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.getAttribute('content');
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    async function subscribe() {
        try {
            const reg = await navigator.serviceWorker.register(base + 'sw.js', { scope: base });
            await navigator.serviceWorker.ready;

            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') { return; }
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey)
                });
            }
            const res = await fetch(base + 'ajax/save_push_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                },
                credentials: 'same-origin',
                body: JSON.stringify(sub.toJSON())
            });
            if (!res.ok) {
                console.warn('Push subscription save failed', res.status);
            }
        } catch (e) {
            console.warn('Push subscription error', e);
        }
    }

    if (document.readyState === 'complete') {
        setTimeout(subscribe, 1500);
    } else {
        window.addEventListener('load', function () { setTimeout(subscribe, 1500); });
    }
})();
