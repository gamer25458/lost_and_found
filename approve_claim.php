<?php
session_start();
require __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/helpers.php';

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
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data   = json_decode(file_get_contents('php://input'), true);
$id     = isset($data['id'])            ? (int) $data['id']            : 0;
$status = isset($data['status'])        ? trim($data['status'])         : '';
$reason = isset($data['reject_reason']) ? trim($data['reject_reason'])  : '';

if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data.']);
    exit();
}

if ($status === 'rejected' && $reason === '') {
    echo json_encode(['success' => false, 'error' => 'Please provide a reason for rejection.']);
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

try {
    $stmt = $mysqli->prepare(
        'SELECT c.id, c.user_id, c.found_item_id, c.status FROM claims c WHERE c.id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$claim) {
        throw new Exception('Claim not found.');
    }
    if ($claim['status'] !== 'pending') {
        throw new Exception('Claim already processed.');
    }

    $mysqli->begin_transaction();

    if ($status === 'approved') {
        $approvedStatus = 'approved';

        $stmt = $mysqli->prepare(
            'UPDATE claims
             SET status = ?, handled_by = ?, reviewed_at = NOW(), admin_reject_reason = NULL
             WHERE id = ?'
        );
        $stmt->bind_param('sii', $approvedStatus, $admin_id, $id);
        $stmt->execute();
        $stmt->close();

        $fid = (int) $claim['found_item_id'];

        $stmt = $mysqli->prepare('UPDATE found_items SET approval_hold = 1 WHERE id = ?');
        $stmt->bind_param('i', $fid);
        $stmt->execute();
        $stmt->close();

        // Auto-reject other pending claims for the same found item
        // 6 bind values: s s i i s i  → status, reason, admin_id, fid, pendingStatus, id
        $rejectedStatus = 'rejected';
        $pendingStatus  = 'pending';
        $autoReason     = 'Another claimant was approved for this found item.';

        $stmt = $mysqli->prepare(
            'UPDATE claims
             SET status = ?, admin_reject_reason = ?, reviewed_at = NOW(), handled_by = ?
             WHERE found_item_id = ? AND status = ? AND id <> ?'
        );
        $stmt->bind_param('ssiisi', $rejectedStatus, $autoReason, $admin_id, $fid, $pendingStatus, $id);
        $stmt->execute();
        $stmt->close();

        $msg = 'Your claim was approved. Please visit the office with a valid ID or passport to collect your item. '
             . 'If the item is a phone or device, bring it unlocked or be ready to verify ownership as instructed at the desk.';

        notify_user(
            $mysqli,
            (int) $claim['user_id'],
            'Claim approved',
            $msg,
            'Approved — visit office with ID / passport',
            'claim_status.html?id=' . $id,
            'claim',
            $id
        );

    } else {
        $rejectedStatus = 'rejected';

        $stmt = $mysqli->prepare(
            'UPDATE claims
             SET status = ?, handled_by = ?, reviewed_at = NOW(), admin_reject_reason = ?
             WHERE id = ?'
        );
        $stmt->bind_param('sisi', $rejectedStatus, $admin_id, $reason, $id);
        $stmt->execute();
        $stmt->close();

        $msg = 'Your claim was declined. Reason: ' . $reason;

        notify_user(
            $mysqli,
            (int) $claim['user_id'],
            'Claim declined',
            $msg,
            'Declined — tap to view details',
            'claim_status.html?id=' . $id,
            'claim',
            $id
        );
    }

    $mysqli->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    try {
        $mysqli->rollback();
    } catch (Throwable $ignored) {
        // Nothing to roll back
    }
    logError('approve_claim', $e);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
