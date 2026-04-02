/**
 * Loads notification center into #notifMount and updates #notifBadge
 * Expects API: backend/fetch_notifications.php, backend/mark_notification_read.php, backend/mark_all_notifications_read.php
 */
(function () {
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }

  window.loadNotifications = function (mountId, badgeId) {
    const mount = document.getElementById(mountId);
    const badge = document.getElementById(badgeId);
    if (!mount) return;

    fetch('backend/fetch_notifications.php')
      .then((r) => r.json())
      .then((data) => {
        if (data.error) return;
        const list = data.notifications || [];
        const unread = data.unread_count || 0;
        if (badge) {
          badge.textContent = unread > 0 ? String(unread) : '';
          badge.style.display = unread > 0 ? 'inline-flex' : 'none';
        }
        mount.innerHTML = '';
        if (list.length === 0) {
          mount.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
          return;
        }
        list.forEach((n) => {
          const row = document.createElement('div');
          row.className = 'notif-row' + (parseInt(n.is_read, 10) === 0 ? ' unread' : '');
          row.innerHTML =
            '<div class="notif-title">' +
            esc(n.title) +
            '</div>' +
            '<div class="notif-preview">' +
            esc(n.preview) +
            '</div>' +
            '<div class="notif-time">' +
            esc(formatTime(n.created_at)) +
            '</div>';
          row.addEventListener('click', function () {
            if (n.link) {
              fetch('backend/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: n.id }),
              }).finally(function () {
                window.location.href = n.link;
              });
            } else {
              fetch('backend/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: n.id }),
              }).then(() => loadNotifications(mountId, badgeId));
            }
          });
          mount.appendChild(row);
        });
      })
      .catch(() => {});
  };

  window.markAllNotificationsRead = function (mountId, badgeId) {
    fetch('backend/mark_all_notifications_read.php', { method: 'POST' })
      .then(() => loadNotifications(mountId, badgeId))
      .catch(() => {});
  };
})();
