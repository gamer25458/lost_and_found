<?php
session_start();
require 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../items.html');
    exit();
}

if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: ../admin.php?error=' . urlencode('Admins review claims; they do not submit them.'));
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$found_id = isset($_POST['found_id']) ? (int) $_POST['found_id'] : 0;
$lost_id  = isset($_POST['lost_id'])  ? (int) $_POST['lost_id']  : 0;
$proof    = trim($_POST['proof_description']      ?? '');
$serial   = trim($_POST['serial_number']          ?? '') ?: null;
$boxNotes = trim($_POST['box_receipt_notes']      ?? '') ?: null;
$devPass  = trim($_POST['device_unlock_password'] ?? '') ?: null;
$providedUnique = trim($_POST['provided_unique_details'] ?? '') ?: null;

if ($found_id <= 0 || $lost_id <= 0 || $proof === '') {
    header('Location: ../claim.html?found_id=' . $found_id . '&lost_id=' . $lost_id
        . '&error=' . urlencode('Please fill in all required fields.'));
    exit();
}

// ── Validate uploaded photos before touching the DB ──────────────────────────
$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$savedPhotoPaths = [];
$allowedExt      = ['jpg', 'jpeg', 'png', 'gif'];

if (!empty($_FILES['photos']['name'][0])) {
    $files = $_FILES['photos'];
    $count = is_array($files['name']) ? count($files['name']) : 0;

    for ($i = 0; $i < min($count, 5); $i++) {
        if ((int) $files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmp = $files['tmp_name'][$i];

        if (getimagesize($tmp) === false) {
            continue; // skip non-images silently
        }

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }

        $filename = 'claim_' . time() . '_' . mt_rand(1000, 9999) . '_' . $i . '.' . $ext;

        if (move_uploaded_file($tmp, $uploadDir . '/' . $filename)) {
            $savedPhotoPaths[] = 'uploads/' . $filename;
        }
    }
}

if (count($savedPhotoPaths) === 0) {
    header('Location: ../claim.html?found_id=' . $found_id . '&lost_id=' . $lost_id
        . '&error=' . urlencode('Please upload at least one proof photo.'));
    exit();
}

// ── DB work ───────────────────────────────────────────────────────────────────
try {
    // Verify lost item belongs to this user and is still open
    $stmt = $mysqli->prepare(
        'SELECT id, user_id, category, resolved FROM lost_items WHERE id = ?'
    );
    $stmt->bind_param('i', $lost_id);
    $stmt->execute();
    $lost = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lost || (int) $lost['user_id'] !== $user_id) {
        throw new Exception('Invalid lost item.');
    }
    if ((int) $lost['resolved'] === 1) {
        throw new Exception('This lost report is already closed.');
    }

    // Verify found item is still active
    $stmt = $mysqli->prepare(
        'SELECT id, category, is_active, approval_hold FROM found_items WHERE id = ?'
    );
    $stmt->bind_param('i', $found_id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$found || (int) $found['is_active'] !== 1) {
        throw new Exception('This found item is no longer available.');
    }

    // Categories must match
    $lostCat  = $lost['category']  ?? '';
    $foundCat = $found['category'] ?? '';

    if ($lostCat === '' || $foundCat === '' || $lostCat !== $foundCat) {
        throw new Exception('Category must match between your lost report and this found item.');
    }

    // Check for an existing claim on this exact pair
    $stmt = $mysqli->prepare(
        'SELECT id, status FROM claims
         WHERE user_id = ? AND found_item_id = ? AND lost_item_id = ?'
    );
    $stmt->bind_param('iii', $user_id, $found_id, $lost_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        if ($existing['status'] === 'pending') {
            throw new Exception('You already have a pending claim for this pair.');
        }
        if ($existing['status'] === 'approved') {
            throw new Exception('You already have an approved claim for this item.');
        }
    }

    // If another claimant was already approved, block new claims
    if ((int) $found['approval_hold'] === 1) {
        $approvedStatus = 'approved';
        $stmt = $mysqli->prepare(
            'SELECT id FROM claims
             WHERE found_item_id = ? AND user_id = ? AND status = ?'
        );
        $stmt->bind_param('iis', $found_id, $user_id, $approvedStatus);
        $stmt->execute();
        $hasApproved = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$hasApproved) {
            throw new Exception('This item is no longer accepting new claims.');
        }
    }

    // ── Transaction ──────────────────────────────────────────────────────────
    $mysqli->begin_transaction();

    $claimId   = 0;
    $pendingStatus = 'pending';

    if ($existing && $existing['status'] === 'rejected') {
        // Re-open the rejected claim with fresh proof
        $claimId = (int) $existing['id'];

        $stmt = $mysqli->prepare(
            'UPDATE claims
             SET status = ?, proof_description = ?, serial_number = ?,
                 box_receipt_notes = ?, device_unlock_password = ?, provided_unique_details = ?,
                 admin_reject_reason = NULL, reviewed_at = NULL, handled_by = NULL
             WHERE id = ?'
        );
        $stmt->bind_param('ssssssi', $pendingStatus, $proof, $serial, $boxNotes, $devPass, $providedUnique, $claimId);
        $stmt->execute();
        $stmt->close();

        // Remove old photos
        $del = $mysqli->prepare('DELETE FROM claim_photos WHERE claim_id = ?');
        $del->bind_param('i', $claimId);
        $del->execute();
        $del->close();

    } else {
        // Brand new claim
        $stmt = $mysqli->prepare(
            'INSERT INTO claims
                (user_id, found_item_id, lost_item_id, status,
                 proof_description, serial_number, box_receipt_notes, device_unlock_password, provided_unique_details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiissssss',
            $user_id, $found_id, $lost_id, $pendingStatus,
            $proof, $serial, $boxNotes, $devPass, $providedUnique
        );
        $stmt->execute();
        $claimId = (int) $mysqli->insert_id;
        $stmt->close();
    }

    // Insert proof photos
    $sort = 0;
    $ins  = $mysqli->prepare(
        'INSERT INTO claim_photos (claim_id, path, sort_order) VALUES (?, ?, ?)'
    );
    foreach ($savedPhotoPaths as $photoPath) {
        $ins->bind_param('isi', $claimId, $photoPath, $sort);
        $ins->execute();
        $sort++;
    }
    $ins->close();

    $mysqli->commit();

    // Notify admins (outside transaction — failure here is non-fatal)
    // Fetch user's full name for notification
    $fullName = '';
    $userStmt = $mysqli->prepare('SELECT full_name FROM users WHERE id = ?');
    $userStmt->bind_param('i', $user_id);
    $userStmt->execute();
    $userStmt->bind_result($fullName);
    $userStmt->fetch();
    $userStmt->close();

    notify_all_admins(
        $mysqli,
        'New claim submitted',
        'User: ' . $fullName . ' submitted a claim for a found item. Please review the Claims tab.',
        'Claim ' . $claimId . ' pending review (by ' . $fullName . ')',
        'admin.php?tab=claims&claim=' . $claimId,
        'claim',
        $claimId
    );

    header('Location: ../claim_status.html?id=' . $claimId);

} catch (Exception $e) {
    try {
        $mysqli->rollback();
    } catch (Throwable $ignored) {
        // Nothing to roll back
    }
    logError('submit_claim', $e);
    header('Location: ../claim.html?found_id=' . $found_id . '&lost_id=' . $lost_id
        . '&error=' . urlencode($e->getMessage()));
}

exit();