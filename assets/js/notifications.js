/* Academics — notification bell
   Reads the per-user feed from ajax/notifications.php and marks items read.
   All state-changing calls are POST + CSRF, matching the endpoint's rules. */
(function () {
    'use strict';

    var bell = document.getElementById('notificationBell');
    if (!bell) return;

    var listEl = document.getElementById('notifList');
    var badge = document.getElementById('notifUnreadBadge');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var base = window.ACADEMICS_BASE || './';

    function post(body) {
        return fetch(base + 'ajax/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf
            },
            body: body
        }).then(function (r) { return r.json(); });
    }

    function render(items) {
        if (!listEl) return;
        if (!items || !items.length) {
            listEl.innerHTML = '<div class="dropdown-item text-muted small">No notifications yet.</div>';
            return;
        }
        listEl.innerHTML = items.map(function (n) {
            var icon = n.icon || 'fa-bell';
            var color = n.color || 'primary';
            var link = n.link ? base + n.link : null;
            var body = '<div class="d-flex gap-2">' +
                '<i class="fas ' + icon + ' text-' + color + ' mt-1"></i>' +
                '<div class="flex-grow-1">' +
                '<div class="fw-semibold small">' + escapeHtml(n.title) + '</div>' +
                '<div class="small text-muted">' + escapeHtml(n.message) + '</div>' +
                '<div class="text-muted" style="font-size:11px">' + n.time + '</div>' +
                '</div>' +
                (n.is_read ? '' : '<span class="badge bg-primary rounded-pill mt-1" title="Unread"></span>') +
                '</div>';
            if (link) {
                return '<a href="' + escapeHtml(link) + '" class="dropdown-item notif-item py-2" data-id="' + n.id + '">' + body + '</a>';
            }
            return '<div class="dropdown-item notif-item py-2" data-id="' + n.id + '">' + body + '</div>';
        }).join('');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function refreshBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
        } else if (badge.parentNode) {
            badge.parentNode.removeChild(badge);
        }
    }

    function load() {
        fetch(base + 'ajax/notifications.php?action=list', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                render(data.items || []);
                refreshBadge(parseInt(data.unread, 10) || 0);
            })
            .catch(function () {
                if (listEl) listEl.innerHTML = '<div class="dropdown-item text-muted small">Could not load notifications.</div>';
            });
    }

    bell.addEventListener('show.bs.dropdown', load);

    document.addEventListener('click', function (ev) {
        var item = ev.target.closest ? ev.target.closest('.notif-item') : null;
        if (item && item.getAttribute('href')) {
            // Mark as read while letting navigation happen.
            post('action=mark_one&csrf_token=' + encodeURIComponent(csrf) + '&id=' + encodeURIComponent(item.getAttribute('data-id')));
            return;
        }
        if (ev.target.closest && ev.target.closest('#markAllReadBtn')) {
            ev.preventDefault();
            post('action=mark_all&csrf_token=' + encodeURIComponent(csrf)).then(function () {
                if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
            });
        }
    });
})();
