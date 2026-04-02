<?php
/**
 * helpers.php — notification helpers
 * Include after config.php (requires $mysqli to be available).
 */

/**
 * Inserts a single notification row for one user.
 */
function notify_user(
    mysqli $mysqli,
    int $userId,
    string $title,
    string $message,
    ?string $preview  = null,
    ?string $link     = null,
    ?string $refType  = null,
    ?int    $refId    = null
): void {
    if ($preview === null) {
        $preview = mb_substr(strip_tags($message), 0, 120);
        if (mb_strlen($message) > 120) {
            $preview .= '…';
        }
    }

    $rt  = $refType ?? '';
    $rid = $refId   ?? 0;

    $stmt = $mysqli->prepare(
        'INSERT INTO notifications
            (user_id, title, message, preview, link, ref_type, ref_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssssi', $userId, $title, $message, $preview, $link, $rt, $rid);
    $stmt->execute();
    $stmt->close();
}

/**
 * Sends a notification to every user with role = 'admin'.
 * Uses a prepared statement — no raw query().
 */
function notify_all_admins(
    mysqli $mysqli,
    string $title,
    string $message,
    ?string $preview = null,
    ?string $link    = null,
    ?string $refType = null,
    ?int    $refId   = null
): void {
    $adminRole = 'admin';
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE role = ?');
    $stmt->bind_param('s', $adminRole);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        notify_user($mysqli, (int) $row['id'], $title, $message, $preview, $link, $refType, $refId);
    }

    $stmt->close();
}

/**
 * Sends a notification to every user who has an unresolved lost report
 * in the given category — used when a new found item is posted.
 */
function notify_users_with_matching_lost_category(
    mysqli $mysqli,
    string $category,
    string $title,
    string $message,
    ?string $preview = null,
    ?string $link    = null,
    ?string $refType = null,
    ?int    $refId   = null
): void {
    if ($category === '') {
        return;
    }

    $stmt = $mysqli->prepare(
        'SELECT DISTINCT user_id FROM lost_items WHERE resolved = 0 AND category = ?'
    );
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        notify_user($mysqli, (int) $row['user_id'], $title, $message, $preview, $link, $refType, $refId);
    }

    $stmt->close();
}
