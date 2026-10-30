/* Academics service worker — web push notifications */
const CACHE_NAME = 'academics-push-v1';

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    let data = {};
    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        try { data = { title: 'Academics', body: event.data.text() }; } catch (e2) { data = { title: 'Academics', body: 'New school notice' }; }
    }

    const title = data.title || 'Academics — School Notice';
    const options = {
        body: data.body || 'You have a new school notification.',
        icon: data.icon || '/assets/img/academics-mark.svg',
        badge: '/assets/img/academics-mark.svg',
        tag: data.tag || 'academics-notice',
        renotify: true,
        requireInteraction: true,
        vibrate: [200, 100, 200],
        data: { url: data.url || '/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url.indexOf(self.location.origin) === 0 && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
