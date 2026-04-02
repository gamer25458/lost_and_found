<?php
session_start();
require 'config.php';
require_once 'helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Admins only.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method.']);
    exit();
}

$claim_id = isset($_POST['claim_id']) ? (int) $_POST['claim_id'] : 0;
$legal    = trim($_POST['legal_name'] ?? '');
$idDoc    = trim($_POST['id_passport_number'] ?? '');

if ($claim_id <= 0 || $legal === '' || $idDoc === '') {
    echo json_encode(['success' => false, 'error' => 'Legal name and ID / passport number are required.']);
    exit();
}

// Validate file upload before doing any DB work
if (!isset($_FILES['handover_photo']) || $_FILES['handover_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'A photo of the user holding the item is required.']);
    exit();
}

$tmp = $_FILES['handover_photo']['tmp_name'];

// Verify it is actually an image (not just a renamed file)
if (getimagesize($tmp) === false) {
    echo json_encode(['success' => false, 'error' => 'Invalid handover photo — not a recognised image.']);
    exit();
}

$ext = strtolower(pathinfo($_FILES['handover_photo']['name'], PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['success' => false, 'error' => 'Handover photo must be JPG, PNG, or GIF.']);
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

try {
    // Fetch the claim and confirm it is approved
    $stmt = $mysqli->prepare(
        'SELECT c.id, c.user_id, c.found_item_id, c.lost_item_id, c.status
         FROM claims c WHERE c.id = ?'
    );
    $stmt->bind_param('i', $claim_id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$claim) {
        throw new Exception('Claim not found.');
    }
    if ($claim['status'] !== 'approved') {
        throw new Exception('Claim must be approved before issuing.');
    }

    // Guard against double-issuing
    $stmt = $mysqli->prepare('SELECT id FROM issued_items WHERE claim_id = ?');
    $stmt->bind_param('i', $claim_id);
    $stmt->execute();
    $alreadyIssued = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($alreadyIssued) {
        throw new Exception('This claim was already issued.');
    }

    // Move uploaded file — done outside the transaction so a disk error
    // does not leave an open transaction.
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'issue_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $destPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $destPath)) {
        throw new Exception('Could not save handover photo.');
    }

    $handoverPath = 'uploads/' . $filename;

    // --- DB transaction ---
    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare(
        'INSERT INTO issued_items
            (claim_id, legal_name, id_passport_number, handover_photo_path, issued_by_admin_id)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssi', $claim_id, $legal, $idDoc, $handoverPath, $admin_id);
    $stmt->execute();
    $stmt->close();

    $fid = (int) $claim['found_item_id'];
    $lid = (int) $claim['lost_item_id'];

    // Deactivate the found item so it no longer appears in listings
    $stmt = $mysqli->prepare('UPDATE found_items SET is_active = 0, approval_hold = 0 WHERE id = ?');
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    $stmt->close();

    // Mark the lost report as resolved
    $foundStatus = 'found';
    $stmt = $mysqli->prepare('UPDATE lost_items SET resolved = 1, status = ? WHERE id = ?');
    $stmt->bind_param('si', $foundStatus, $lid);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    // Notify the user outside the transaction (failure here is non-fatal)
    notify_user(
        $mysqli,
        (int) $claim['user_id'],
        'Item issued',
        'Your item has been recorded as collected. You can find it under your Collected items tab.',
        'Issued — item collected',
        'items.html?tab=collected',
        'issued',
        $claim_id
    );

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Roll back only if a transaction was started
    try {
        $mysqli->rollback();
    } catch (Throwable $ignored) {
        // Nothing to roll back
    }
    logError('issue_handover', $e);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}