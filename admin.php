<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['is_admin'] !== true) {
    header('Location: login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fc; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 260px; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.06); padding: 20px 0; position: fixed; height: 100vh; z-index: 50; overflow-y: auto; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid #eee; }
        .sidebar-header h2 { color: #0a3d62; font-size: 20px; }
        .sidebar-menu { list-style: none; margin-top: 16px; }
        .sidebar-menu li { padding: 12px 22px; color: #555; cursor: pointer; border-radius: 0 8px 8px 0; font-size: 14px; }
        .sidebar-menu li:hover, .sidebar-menu li.active { background: #f0f4ff; color: #0a3d62; }
        .sidebar-menu a { color: inherit; text-decoration: none; display: block; }

        /* Topbar */
        .topbar { height: 64px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; position: fixed; top: 0; left: 260px; right: 0; z-index: 40; gap: 16px; }

        /* Main content */
        .main-content { margin-left: 260px; padding: 84px 32px 40px; flex: 1; width: calc(100% - 260px); }
        .panel { display: none; }
        .panel.active { display: block; }

        /* Filters and grid styles */
        .filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; align-items: center; }
        .filters input, .filters select { padding: 8px 12px; border-radius: 8px; border: 1px solid #ccc; min-width: 160px; font-size: 14px; }
        .filters button { padding: 8px 16px; background: #0a3d62; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
        .card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); cursor: pointer; transition: box-shadow .18s, transform .18s; }
        .card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.11); transform: translateY(-2px); }
        .card img.thumb { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        .card h4 { font-size: 15px; color: #0a3d62; margin-bottom: 6px; }
        .card .meta { font-size: 12px; color: #777; }

        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.lost  { background: #fff3e0; color: #e65100; }
        .badge.found { background: #e8f5e9; color: #2e7d32; }
        .badge.pending  { background: #fff8e1; color: #f57c00; }
        .badge.approved { background: #e8f5e9; color: #2e7d32; }
        .badge.rejected { background: #fdecea; color: #c0392b; }

        /* Directory layout for reported items */
        .dir-layout { display: flex; gap: 0; min-height: 500px; }
        .dir-sidebar { width: 220px; flex-shrink: 0; border-right: 1px solid #eef2f7; padding-right: 0; }
        .dir-sidebar-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #aaa; padding: 0 16px 10px; }
        .dir-cat-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; cursor: pointer; border-radius: 8px 0 0 8px; color: #444; font-size: 14px; transition: background .15s; margin-right: -1px; position: relative; border: 1px solid transparent; border-right: none; }
        .dir-cat-item:hover { background: #f5f7ff; color: #0a3d62; }
        .dir-cat-item.active { background: #fff; color: #0a3d62; font-weight: 600; border-color: #eef2f7; border-right-color: #fff; z-index: 2; }
        .dir-cat-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .dir-cat-icon.lost-icon  { background: #fff3e0; }
        .dir-cat-icon.found-icon { background: #e8f5e9; }
        .dir-cat-star { margin-left: auto; color: #ccc; font-size: 13px; }
        .dir-cat-item:hover .dir-cat-star, .dir-cat-item.active .dir-cat-star { color: #0a3d62; }
        .dir-pane { flex: 1; padding: 0 0 0 24px; min-width: 0; }
        .dir-pane-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .dir-pane-title { font-size: 18px; font-weight: 700; color: #0a3d62; }
        .dir-pane-filters { display: flex; gap: 8px; flex-wrap: wrap; }
        .dir-pane-filters input, .dir-pane-filters select { padding: 7px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; min-width: 140px; }
        .dir-pane-filters button { padding: 7px 16px; background: #0a3d62; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; }

        .dir-card { background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%); border: 1px solid #e7eef9; border-radius: 18px; padding: 16px; display: grid; grid-template-columns: 100px 1fr; gap: 16px; transition: box-shadow .18s, transform .18s; cursor: default; align-items: start; }
        .dir-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.12); transform: translateY(-2px); }
        .dir-card-image-wrap { position: relative; min-width: 100px; min-height: 110px; overflow: hidden; border-radius: 16px; background: #eef6ff; cursor: pointer; }
        .dir-card-thumb, .dir-card-icon-placeholder { width: 100%; height: 100%; display: block; object-fit: cover; border-radius: 16px; }
        .dir-card-overlay { position: absolute; inset: 0; display: flex; align-items: flex-end; justify-content: center; padding: 10px; color: #fff; font-size: 12px; font-weight: 700; background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.35) 100%); opacity: 0; transition: opacity .18s; pointer-events: none; }
        .dir-card-image-wrap:hover .dir-card-overlay { opacity: 1; }
        .dir-card h4 { font-size: 15px; font-weight: 700; color: #102a43; margin: 0 0 4px; }
        .dir-card-desc { font-size: 13px; color: #475569; line-height: 1.55; max-height: 3.1em; overflow: hidden; }
        .dir-card-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .dir-card-score { background: #e8f6ff; color: #0b69a3; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid #cfe7fb; }
        .dir-card-footer { display: flex; align-items: center; gap: 8px; justify-content: space-between; flex-wrap: wrap; margin-top: 6px; padding-top: 10px; border-top: 1px solid #e7eef9; }
        .dir-card-tag { display: inline-flex; align-items: center; gap: 6px; background: #eef4ff; border: 1px solid #dbe5ff; border-radius: 999px; padding: 5px 12px; font-size: 12px; color: #1f3c88; font-weight: 700; }
        .dir-card-del { background: #fff5f5; border: 1px solid #f3d7d9; border-radius: 10px; padding: 7px 12px; font-size: 12px; color: #b91c1c; font-weight: 700; cursor: pointer; transition: background .15s, transform .15s; white-space: nowrap; }
        .dir-card-del:hover { background: #fee2e2; transform: translateY(-1px); }
        .dir-card-status { margin-top: 2px; font-size: 11px; color: #475569; }
        .image-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 400; align-items: center; justify-content: center; padding: 18px; }
        .image-modal-bg.open { display: flex; }
        .image-modal { background: #fff; border-radius: 16px; max-width: 860px; width: 100%; max-height: 92vh; overflow: hidden; position: relative; }
        .image-modal img { width: 100%; max-height: 80vh; object-fit: contain; display: block; background: #f8fafc; }
        .image-modal .caption { padding: 16px; font-size: 14px; color: #334155; }
        .image-modal button.close-modal { position: absolute; right: 14px; top: 14px; border: none; background: rgba(255,255,255,0.9); color: #111; font-size: 16px; border-radius: 50%; width: 34px; height: 34px; cursor: pointer; }
        .image-modal .caption small { color: #64748b; display: block; margin-top: 6px; }

        /* Modals */
        .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 300; align-items: center; justify-content: center; padding: 20px; }
        .modal-bg.open { display: flex; }
        .modal { background: #fff; border-radius: 12px; max-width: 920px; width: 100%; max-height: 92vh; overflow-y: auto; padding: 24px; }
        .modal .hero-img { width: 100%; max-height: 360px; object-fit: contain; background: #f5f5f5; border-radius: 10px; }
        .strip { display: flex; gap: 8px; overflow-x: auto; margin-top: 12px; padding-bottom: 8px; }
        .strip img { height: 72px; width: auto; border-radius: 8px; cursor: pointer; object-fit: cover; }
        .actions { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-start; }
        .actions textarea { flex: 1; min-width: 200px; min-height: 70px; padding: 8px; border-radius: 8px; border: 1px solid #ccc; font-size: 14px; }
        .msg-ok  { color: #27ae60; margin-top: 10px; font-size: 14px; }
        .msg-err { color: #c0392b; margin-top: 10px; font-size: 14px; }

        /* Tables */
        table.simple { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; }
        table.simple th, table.simple td { padding: 12px 14px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
        table.simple th { background: #f7fbff; font-weight: 700; color: #1b3a57; }

        /* Issue form */
        .issue-form label { display: block; font-weight: 600; margin-top: 12px; font-size: 14px; color: #333; }
        .issue-form input[type=text], .issue-form input[type=file] { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 8px; margin-top: 4px; font-size: 14px; }

        .state-msg { text-align: center; padding: 50px 20px; color: #888; font-size: 14px; }

        /* Users table specific */
        .delete-user-btn { background: #fee2e2; border: 1px solid #f3d7d9; padding: 6px 12px; border-radius: 8px; cursor: pointer; color: #b91c1c; font-size: 12px; font-weight: 600; }
        .delete-user-btn:hover { background: #fecaca; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Lost & Found</h2>
        <p style="color:#888;font-size:13px;">Admin panel</p>
    </div>
    <ul class="sidebar-menu">
        <li data-tab="reported">🗃️Reported items</li>
        <li data-tab="claims">📥 Claims</li>
        <li data-tab="issue">✅ Issue item</li>
        <li data-tab="issued">📦 Issued items</li>
        <li data-tab="users">👥 Users</li>
        <li><a href="found.html">➕ Report found item</a></li>
        <li style="margin-top:24px;"><a href="backend/logout.php" style="color:#e74c3c;">🚪 Log out</a></li>
    </ul>
</div>

<div class="topbar">
    <div style="font-weight:700;color:#0a3d62;font-size:18px;">Lost & Found</div>
    <div style="display:flex;gap:16px;align-items:center;flex:1;margin-left:32px;">
        <a href="found.html" style="text-decoration:none;color:#0a3d62;font-size:14px;padding:8px 12px;border-radius:8px;cursor:pointer;border:1px solid #ddd;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='transparent'">➕ Report found item</a>
        <a href="lost.html" style="text-decoration:none;color:#0a3d62;font-size:14px;padding:8px 12px;border-radius:8px;cursor:pointer;border:1px solid #ddd;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='transparent'">➕ Report lost item</a>
        <div style="position:relative;display:inline-block;">
            <button id="reportedDropdownBtn" style="background:#0a3d62;color:#fff;padding:8px 12px;border:none;border-radius:8px;cursor:pointer;font-size:14px;" onclick="toggleReportedDropdown()">📋 Reported items ▼</button>
            <div id="reportedDropdown" style="display:none;position:absolute;top:100%;left:0;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);min-width:180px;z-index:100;margin-top:4px;">
                <a href="#" onclick="switchReportedTab('lost');return false;" style="display:block;padding:10px 16px;color:#0a3d62;text-decoration:none;border-bottom:1px solid #eee;font-size:14px;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='transparent'">Lost items</a>
                <a href="#" onclick="switchReportedTab('found');return false;" style="display:block;padding:10px 16px;color:#0a3d62;text-decoration:none;font-size:14px;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='transparent'">Found items</a>
            </div>
        </div>
        <button id="topbarUsersBtn" style="background:#0a3d62;color:#fff;padding:8px 12px;border:none;border-radius:8px;cursor:pointer;font-size:14px;">👥 Users</button>
    </div>
    <div class="nav-notif-wrap">
        <button type="button" class="notif-bell" id="notifBell">🔔 <span class="notif-badge" id="notifBadge"></span></button>
        <div class="notif-panel" id="notifPanel">
            <div class="notif-actions"><button type="button" id="markAllRead">Mark all read</button></div>
            <div id="notifMount"></div>
        </div>
    </div>
    <span style="font-weight:600;color:#0a3d62;font-size:14px;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
</div>

<div class="main-content">
    <div id="banner" style="display:none;padding:12px 16px;background:#eafaf1;color:#27ae60;border-radius:8px;margin-bottom:16px;font-size:14px;"></div>

    <!-- Reported items panel -->
    <div id="panel-reported" class="panel">
        <h1 style="color:#0a3d62;margin-bottom:20px;">Reported items</h1>
        <div class="dir-layout">
            <div class="dir-sidebar">
                <div class="dir-sidebar-title">Categories</div>
                <div class="dir-cat-item active" data-type="lost" id="catLost">
                    <div class="dir-cat-icon lost-icon">🔴</div>
                    <span>Lost items</span>
                    <span class="dir-cat-star">☆</span>
                </div>
                <div class="dir-cat-item" data-type="found" id="catFound">
                    <div class="dir-cat-icon found-icon">🟢</div>
                    <span>Found items</span>
                    <span class="dir-cat-star">☆</span>
                </div>
                <div style="margin-top:24px;padding:0 16px;">
                    <div class="dir-sidebar-title" style="padding-left:0;">All Categories</div>
                    <div id="catFilterList" style="display:flex;flex-direction:column;gap:2px;margin-top:4px;"></div>
                </div>
            </div>
            <div class="dir-pane">
                <div class="dir-pane-header">
                    <span class="dir-pane-title" id="dirPaneTitle">Lost items</span>
                    <div class="dir-pane-filters">
                        <select id="repCat">
                            <option value="">All categories</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Accessories">Accessories</option>
                            <option value="Documents">Documents</option>
                            <option value="Keys">Keys</option>
                            <option value="Bag">Bag</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" id="repSearch" placeholder="Search…">
                        <button type="button" id="repBtn">Search</button>
                    </div>
                </div>
                <div class="grid" id="repGrid"><div class="state-msg">Loading…</div></div>
            </div>
        </div>
    </div>

    <!-- Claims panel -->
    <div id="panel-claims" class="panel">
        <h1 style="color:#0a3d62;margin-bottom:12px;">Claims</h1>
        <div class="filters">
            <select id="claimFilter">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="all">All</option>
            </select>
            <button type="button" id="claimReload">Reload</button>
        </div>
        <div class="grid" id="claimsGrid"></div>
    </div>

    <!-- Issue item panel -->
    <div id="panel-issue" class="panel">
        <h1 style="color:#0a3d62;margin-bottom:12px;">Issue item (handover)</h1>
        <p style="color:#666;margin-bottom:16px;font-size:14px;">Approved claims appear here. After ID check and physical handover, complete the form below.</p>
        <div id="issueList"></div>
        <div id="issueFormWrap" style="display:none;margin-top:24px;padding:20px;background:#fff;border-radius:10px;max-width:520px;box-shadow:0 2px 10px rgba(0,0,0,0.07);">
            <h3 style="color:#0a3d62;margin-bottom:12px;">Issue claim #<span id="issueClaimId"></span></h3>
            <form id="issueForm" class="issue-form" enctype="multipart/form-data">
                <input type="hidden" name="claim_id" id="issueClaimField">
                <label>Legal name (as on ID)</label>
                <input type="text" name="legal_name" required>
                <label>ID / passport number</label>
                <input type="text" name="id_passport_number" required>
                <label>Photo of user holding the item</label>
                <input type="file" name="handover_photo" accept="image/*" required>
                <button type="submit" class="btn" style="margin-top:16px;">Complete issuing</button>
            </form>
            <p id="issueMsg"></p>
        </div>
    </div>

    <!-- Issued items panel -->
    <div id="panel-issued" class="panel">
        <h1 style="color:#0a3d62;margin-bottom:12px;">Issued items</h1>
        <div id="issuedList"></div>
    </div>

    <!-- Users panel -->
    <div id="panel-users" class="panel">
        <h1 style="color:#0a3d62;margin-bottom:20px;">Registered Users</h1>
        <div id="usersList">
            <div class="state-msg">Loading users…</div>
        </div>
    </div>
</div>

<!-- ── CLAIM MODAL (with unique details) ── -->
<div class="modal-bg" id="claimModal">
    <div class="modal">
        <button type="button" onclick="document.getElementById('claimModal').classList.remove('open')"
            style="float:right;border:none;background:#eee;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:13px;">✕ Close</button>
        <h3 id="mTitle" style="margin-bottom:6px;color:#0a3d62;"></h3>
        <p id="mSub" style="color:#666;font-size:13px;margin-bottom:12px;"></p>
        <img id="mHero" class="hero-img" alt="" style="display:none;">
        <div class="strip" id="mStrip"></div>
        <p id="mProof" style="margin-top:12px;font-size:14px;line-height:1.5;"></p>
        <p id="mSerial" style="font-size:14px;margin-top:6px;"></p>
        <p id="mDev" style="font-size:13px;color:#888;margin-top:4px;"></p>

        <!-- Unique details comparison block -->
        <div id="uniqueDetailsArea" style="background:#f9f9f9;padding:12px;border-radius:8px;margin:12px 0;border-left:4px solid #0a3d62;">
            <p><strong>🔍 Unique details verification</strong></p>
            <p><strong>Stored with lost report:</strong><br><span id="lostUniqueDetails"></span></p>
            <p><strong>Provided by claimant:</strong><br><span id="providedUniqueDetails"></span></p>
            <div class="hint" style="font-size:12px;color:#666;margin-top:8px;">Only the rightful owner should know these details. Compare carefully.</div>
        </div>

        <div class="actions" id="mActions"></div>
        <textarea id="mReject" placeholder="Rejection reason (required to reject)" style="width:100%;margin-top:10px;min-height:70px;padding:8px;border-radius:8px;border:1px solid #ccc;font-size:14px;"></textarea>
    </div>
</div>

<div class="image-modal-bg" id="imagePreviewModal">
    <div class="image-modal">
        <button type="button" class="close-modal" onclick="document.getElementById('imagePreviewModal').classList.remove('open')">✕</button>
        <img id="imagePreviewSrc" src="" alt="Preview">
        <div class="caption"><strong id="imagePreviewTitle"></strong><br><small>Tap outside or the close button to dismiss.</small></div>
    </div>
</div>

<script src="js/notifications.js"></script>
<script>
(function () {
  'use strict';

  // Helper functions
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function imgThumb(path) {
    if (!path) return '';
    return 'uploads/' + esc(path.split('/').pop());
  }

  // Tab switching (includes 'users')
  function showTab(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.sidebar-menu li[data-tab]').forEach(li => {
      li.classList.toggle('active', li.getAttribute('data-tab') === name);
    });
    const el = document.getElementById('panel-' + name);
    if (el) el.classList.add('active');
    if (name === 'reported') loadReported();
    if (name === 'claims')   loadClaims();
    if (name === 'issue')    loadIssueList();
    if (name === 'issued')   loadIssued();
    if (name === 'users')    loadUsers();
  }

  // ---------- USERS ----------
  function loadUsers() {
    const container = document.getElementById('usersList');
    container.innerHTML = '<div class="state-msg">Loading users…</div>';
    fetch('backend/fetch_users.php')
      .then(r => r.json())
      .then(data => {
        container.innerHTML = '';
        if (data.error) {
          container.innerHTML = '<div class="state-msg">' + esc(data.error) + '</div>';
          return;
        }
        if (!data.length) {
          container.innerHTML = '<div class="state-msg">No users found.</div>';
          return;
        }
        const table = document.createElement('table');
        table.className = 'simple';
        table.innerHTML = '<thead> <th>ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Registered</th><th>Action</th> </thead>';
        const tbody = document.createElement('tbody');
        const currentUserId = <?php echo (int) $_SESSION['user_id']; ?>;
        data.forEach(user => {
          const tr = document.createElement('tr');
          const deleteBtn = (user.id != currentUserId)
            ? '<button class="delete-user-btn" data-id="' + user.id + '">Delete</button>'
            : '—';
          tr.innerHTML = `
             <td>${esc(user.id)}</td>
             <td>${esc(user.full_name)}</td>
             <td>${esc(user.email)}</td>
             <td>${esc(user.role)}</td>
             <td>${esc(user.created_at)}</td>
             <td>${deleteBtn}</td>
          `;
          tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        container.appendChild(table);
        container.querySelectorAll('.delete-user-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            const userId = parseInt(btn.getAttribute('data-id'), 10);
            if (!confirm('Delete user? This will remove all their reports and claims.')) return;
            fetch('backend/delete_user.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: userId })
            })
              .then(r => r.json())
              .then(res => {
                if (res.success) loadUsers();
                else alert(res.error || 'Delete failed.');
              })
              .catch(() => alert('Request failed.'));
          });
        });
      })
      .catch(() => {
        container.innerHTML = '<div class="state-msg">Could not load users.</div>';
      });
  }

  // ---------- Reported items ----------
  let currentRepType = 'lost';
  function setRepType(type) {
    currentRepType = type;
    document.querySelectorAll('.dir-cat-item').forEach(el => {
      el.classList.toggle('active', el.getAttribute('data-type') === type);
    });
    document.getElementById('dirPaneTitle').textContent =
      type === 'lost' ? 'Lost items (reported by users)' : 'Found items (reported by admins)';
    loadReported();
  }

  function loadReported() {
    const cat  = document.getElementById('repCat').value;
    const q    = document.getElementById('repSearch').value.trim();
    const url  = 'backend/fetch_items.php?admin=1&type=' + encodeURIComponent(currentRepType)
               + '&category=' + encodeURIComponent(cat)
               + '&search=' + encodeURIComponent(q);
    const grid = document.getElementById('repGrid');
    grid.innerHTML = '<div class="state-msg">Loading…</div>';
    fetch(url)
      .then(r => r.json())
      .then(data => {
        grid.innerHTML = '';
        if (data.error) {
          grid.innerHTML = '<div class="state-msg">' + esc(data.error) + '</div>';
          return;
        }
        if (!data.length) {
          grid.innerHTML = '<div class="state-msg">No items found.</div>';
          return;
        }
        data.forEach(it => {
          const card = document.createElement('div');
          card.className = 'dir-card';
          const typeLabel = (it.type || currentRepType).toLowerCase();
          const catLabel  = it.category || 'Uncategorised';
          const scoreBadge = it.match_score != null
            ? '<div class="dir-card-score">Match score: ' + esc(it.match_score) + '%</div>'
            : '<div class="dir-card-score" style="background:#f0f4ff;color:#475569;border-color:#d7e3f0;">No matches yet</div>';
          const deleteBtn = '<button class="dir-card-del" data-id="' + esc(it.id) + '" data-type="' + esc(typeLabel) + '">Delete</button>';
          const imgHtml = it.image_path
            ? '<div class="dir-card-image-wrap"><img class="dir-card-thumb" src="' + imgThumb(it.image_path) + '" alt=""><div class="dir-card-overlay">View photo</div></div>'
            : '<div class="dir-card-image-wrap"><div class="dir-card-icon-placeholder">' + (typeLabel === 'lost' ? '🔎' : '📦') + '</div></div>';
          card.innerHTML =
            imgHtml +
            '<div>' +
              '<span class="badge ' + esc(typeLabel) + '">' + esc(typeLabel) + '</span>' +
              '<h4>' + esc(it.item_name) + '</h4>' +
              '<div class="dir-card-desc">' + esc(it.description || '—') + '</div>' +
              '<div class="dir-card-meta">' + scoreBadge + '</div>' +
              '<div class="dir-card-footer">' +
                '<div class="dir-card-tag">' +
                  '<span style="font-size:11px;">🏷</span>' +
                  '<span>' + esc(catLabel) + '</span>' +
                '</div>' +
                deleteBtn +
              '</div>' +
              '<div class="dir-card-status">' + esc(it.full_name || '') + ' · ' + esc(it.date || '') + '</div>' +
            '</div>';
          card.querySelector('.dir-card-del').addEventListener('click', e => {
            e.stopPropagation();
            const itemId   = parseInt(card.querySelector('.dir-card-del').getAttribute('data-id'), 10);
            const itemType = card.querySelector('.dir-card-del').getAttribute('data-type');
            if (!confirm('Delete this ' + itemType + ' item? This cannot be undone.')) return;
            fetch('backend/delete_item.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: itemId, type: itemType })
            })
              .then(r => r.json())
              .then(res => {
                if (res.success) card.remove();
                else alert(res.error || 'Delete failed.');
              })
              .catch(() => alert('Request failed.'));
          });
          const thumb = card.querySelector('.dir-card-thumb');
          const wrapper = card.querySelector('.dir-card-image-wrap');
          if (thumb && wrapper) {
            wrapper.addEventListener('click', () => openImagePreview(thumb.src, it.item_name));
          }
          grid.appendChild(card);
        });
      })
      .catch(() => {
        grid.innerHTML = '<div class="state-msg">Could not load items.</div>';
      });
  }

  function openImagePreview(src, title) {
    document.getElementById('imagePreviewSrc').src = src;
    document.getElementById('imagePreviewTitle').textContent = title || 'Photo preview';
    document.getElementById('imagePreviewModal').classList.add('open');
  }
  document.getElementById('imagePreviewModal').addEventListener('click', e => {
    if (e.target === this) this.classList.remove('open');
  });

  // Dropdown handlers
  window.toggleReportedDropdown = function() {
    const dropdown = document.getElementById('reportedDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  };
  window.switchReportedTab = function(type) {
    setRepType(type);
    document.getElementById('reportedDropdown').style.display = 'none';
    showTab('reported');
  };
  document.addEventListener('click', e => {
    const dropdown = document.getElementById('reportedDropdown');
    const btn = document.getElementById('reportedDropdownBtn');
    if (!e.target.closest('#reportedDropdownBtn') && !e.target.closest('#reportedDropdown')) {
      dropdown.style.display = 'none';
    }
  });
  document.querySelectorAll('.dir-cat-item').forEach(el => {
    el.addEventListener('click', () => setRepType(el.getAttribute('data-type')));
  });
  document.getElementById('repBtn').addEventListener('click', loadReported);
  document.getElementById('repSearch').addEventListener('keydown', e => { if (e.key === 'Enter') loadReported(); });

  // ---------- Claims ----------
  function loadClaims() {
    const st   = document.getElementById('claimFilter').value;
    const grid = document.getElementById('claimsGrid');
    grid.innerHTML = '<div class="state-msg">Loading…</div>';
    fetch('backend/fetch_claims.php?status=' + encodeURIComponent(st))
      .then(r => r.json())
      .then(data => {
        grid.innerHTML = '';
        if (data.error) { grid.innerHTML = '<div class="state-msg">' + esc(data.error) + '</div>'; return; }
        if (!data.length) { grid.innerHTML = '<div class="state-msg">No claims.</div>'; return; }
        data.forEach(c => {
          const card = document.createElement('div');
          card.className = 'card';
          const th = c.thumb ? '<img class="thumb" src="' + imgThumb(c.thumb) + '" alt="">' : '';
          card.innerHTML =
            th +
            '<span class="badge ' + esc(c.status) + '">' + esc(c.status) + '</span>' +
            '<h4>Claim #' + esc(c.id) + '</h4>' +
            '<div class="meta">' + esc(c.full_name) + ' · ' + esc(c.found_name) + '</div>' +
            '<div class="meta">' + esc(c.created_at) + '</div>';
          card.addEventListener('click', () => openClaimModal(c.id));
          grid.appendChild(card);
        });
      })
      .catch(() => grid.innerHTML = '<div class="state-msg">Failed to load.</div>');
  }

  function openClaimModal(id) {
    fetch('backend/fetch_claim_detail.php?id=' + id)
      .then(r => r.json())
      .then(c => {
        if (c.error) { alert(c.error); return; }
        document.getElementById('mTitle').textContent = 'Claim #' + c.id + ' (' + c.status + ')';
        document.getElementById('mSub').textContent   = c.full_name + ' · ' + c.email + '  |  Found: ' + c.found_name + '  |  Lost: ' + c.lost_name;
        const photos = c.photos || [];
        const hero   = photos.length ? imgThumb(photos[0].path) : '';
        const mHero  = document.getElementById('mHero');
        mHero.style.display = hero ? 'block' : 'none';
        mHero.src = hero;
        const strip = document.getElementById('mStrip');
        strip.innerHTML = '';
        photos.forEach(p => {
          const im = document.createElement('img');
          im.src = imgThumb(p.path);
          im.addEventListener('click', () => { mHero.src = im.src; });
          strip.appendChild(im);
        });
        document.getElementById('mProof').innerHTML = '<strong>Proof:</strong> ' + esc(c.proof_description);
        document.getElementById('mSerial').textContent = c.serial_number ? ('Serial: ' + c.serial_number) : '';
        document.getElementById('mDev').textContent    = c.device_unlock_password ? ('Device password on file') : '';
        // unique details
        document.getElementById('lostUniqueDetails').innerHTML = esc(c.lost_unique_details || '—');
        document.getElementById('providedUniqueDetails').innerHTML = esc(c.provided_unique_details || '—');
        const actions = document.getElementById('mActions');
        actions.innerHTML = '';
        document.getElementById('mReject').value = '';
        if (c.status === 'pending') {
          const ap = document.createElement('button');
          ap.className = 'btn';
          ap.textContent = 'Approve';
          ap.addEventListener('click', () => doApprove(id, 'approved'));
          const rj = document.createElement('button');
          rj.className = 'btn secondary';
          rj.textContent = 'Reject';
          rj.addEventListener('click', () => {
            const reason = document.getElementById('mReject').value.trim();
            if (!reason) { alert('Enter a rejection reason first.'); return; }
            doApprove(id, 'rejected', reason);
          });
          actions.appendChild(ap);
          actions.appendChild(rj);
        }
        document.getElementById('claimModal').classList.add('open');
      })
      .catch(() => alert('Could not load claim.'));
  }

  function doApprove(id, status, reason) {
    fetch('backend/approve_claim.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id, status: status, reject_reason: reason || '' })
    })
      .then(r => r.json())
      .then(res => {
        if (!res.success) { alert(res.error || 'Failed'); return; }
        document.getElementById('claimModal').classList.remove('open');
        loadClaims();
      })
      .catch(() => alert('Request failed'));
  }

  document.getElementById('claimReload').addEventListener('click', loadClaims);
  document.getElementById('claimFilter').addEventListener('change', loadClaims);

  // ---------- Issue & Issued ----------
  function loadIssueList() {
    const box = document.getElementById('issueList');
    box.innerHTML = '<div class="state-msg">Loading…</div>';
    fetch('backend/fetch_approved_unissued.php')
      .then(r => r.json())
      .then(rows => {
        box.innerHTML = '';
        if (rows.error) { box.innerHTML = '<div class="state-msg">' + esc(rows.error) + '</div>'; return; }
        if (!rows.length) { box.innerHTML = '<div class="state-msg">No approved claims waiting for handover.</div>'; return; }
        const t  = document.createElement('table');
        t.className = 'simple';
        t.innerHTML = '<thead> <th>Claim</th><th>User</th><th>Found item</th><th>Action</th> </thead>';
        const tb = document.createElement('tbody');
        rows.forEach(r => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
             <td>#${esc(r.id)}</td>
             <td>${esc(r.full_name)}</td>
             <td>${esc(r.found_name)}</td>
             <td><button type="button" class="btn issue-btn" data-id="${esc(r.id)}">Issue</button></td>
          `;
          tb.appendChild(tr);
        });
        t.appendChild(tb);
        box.appendChild(t);
        box.querySelectorAll('.issue-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const cid = parseInt(btn.getAttribute('data-id'), 10);
            document.getElementById('issueFormWrap').style.display = 'block';
            document.getElementById('issueClaimId').textContent = String(cid);
            document.getElementById('issueClaimField').value = String(cid);
            document.getElementById('issueMsg').textContent = '';
            document.getElementById('issueFormWrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        });
      })
      .catch(() => box.innerHTML = '<div class="state-msg">Failed to load.</div>');
  }

  document.getElementById('issueForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const legalName = this.querySelector('[name="legal_name"]').value.trim();
    const idNumber  = this.querySelector('[name="id_passport_number"]').value.trim();
    const msg = document.getElementById('issueMsg');
    if (!legalName || !idNumber) {
        msg.textContent = 'Legal name and ID / passport number are required.';
        msg.className = 'msg-err';
        return;
    }
    const fd = new FormData(this);
    msg.textContent = 'Saving…';
    msg.className = '';
    fetch('backend/issue_handover.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) { msg.textContent = res.error || 'Error'; msg.className = 'msg-err'; return; }
            msg.textContent = 'Issued successfully.';
            msg.className   = 'msg-ok';
            document.getElementById('issueForm').reset();
            document.getElementById('issueFormWrap').style.display = 'none';
            loadIssueList();
            loadIssued();
        })
        .catch(function () { msg.textContent = 'Request failed'; msg.className = 'msg-err'; });
  });

  function loadIssued() {
    const box = document.getElementById('issuedList');
    box.innerHTML = '<div class="state-msg">Loading…</div>';
    fetch('backend/fetch_issued_admin.php')
      .then(r => r.json())
      .then(rows => {
        if (rows.error) { box.innerHTML = '<div class="state-msg">' + esc(rows.error) + '</div>'; return; }
        if (!rows.length) { box.innerHTML = '<div class="state-msg">No issued records yet.</div>'; return; }
        const t  = document.createElement('table');
        t.className = 'simple';
        t.innerHTML = '<thead> <th>Issued</th><th>User</th><th>Item</th><th>Legal name</th><th>ID doc</th><th>Photo</th> </thead>';
        const tb = document.createElement('tbody');
        rows.forEach(r => {
          const tr = document.createElement('tr');
          const ph = r.handover_photo_path
            ? '<img src="uploads/' + esc(r.handover_photo_path.split('/').pop()) + '" class="item-image previewable" alt="Handover photo" style="max-width:60px;max-height:60px;cursor:pointer;border-radius:8px;" data-src="uploads/' + esc(r.handover_photo_path.split('/').pop()) + '" data-title="' + esc(r.found_item_name) + '">'
            : '—';
          tr.innerHTML = `
             <td>${esc(r.issued_at)}</td>
             <td>${esc(r.user_account_name)}</td>
             <td>${esc(r.found_item_name)}</td>
             <td>${esc(r.legal_name)}</td>
             <td>${esc(r.id_passport_number)}</td>
             <td>${ph}</td>
          `;
          tb.appendChild(tr);
        });
        t.appendChild(tb);
        box.innerHTML = '';
        box.appendChild(t);
        t.querySelectorAll('.item-image.previewable').forEach(img => {
          img.addEventListener('click', e => {
            e.preventDefault();
            openImagePreview(img.getAttribute('data-src'), img.getAttribute('data-title'));
          });
        });
      })
      .catch(() => box.innerHTML = '<div class="state-msg">Failed.</div>');
  }

  // Sidebar clicks
  document.querySelectorAll('.sidebar-menu li[data-tab]').forEach(li => {
    li.addEventListener('click', () => {
      const t = li.getAttribute('data-tab');
      showTab(t);
      history.replaceState({}, '', 'admin.php?tab=' + encodeURIComponent(t));
    });
  });

  // Top bar Users button
  document.getElementById('topbarUsersBtn').addEventListener('click', () => {
    showTab('users');
    history.replaceState({}, '', 'admin.php?tab=users');
  });

  // Notifications
  document.getElementById('notifBell').addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('notifPanel').classList.toggle('open');
    loadNotifications('notifMount', 'notifBadge');
  });
  document.getElementById('markAllRead').addEventListener('click', e => {
    e.stopPropagation();
    markAllNotificationsRead('notifMount', 'notifBadge');
  });
  document.addEventListener('click', () => {
    document.getElementById('notifPanel').classList.remove('open');
  });

  // Initial load
  const params = new URLSearchParams(window.location.search);
  if (params.get('success')) {
    const b = document.getElementById('banner');
    b.style.display = 'block';
    b.textContent = 'Found item reported successfully.';
  }
  const tab = params.get('tab') || 'reported';
  showTab(tab);
  const deep = parseInt(params.get('claim') || '0', 10);
  if (deep) {
    showTab('claims');
    openClaimModal(deep);
  }
  loadNotifications('notifMount', 'notifBadge');
})();
</script>
</body>
</html>