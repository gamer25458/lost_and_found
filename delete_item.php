<?php
// delete_item.php — admin-only endpoint to delete a lost or found item


session_start();
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['type'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data.']);
    exit();
}

$id = (int) $data['id'];

// Whitelist table name — never interpolate user input directly into SQL
if ($data['type'] === 'lost') {
    $table = 'lost_items';
} elseif ($data['type'] === 'found') {
    $table = 'found_items';
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid item type.']);
    exit();
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID.']);
    exit();
}


try {
    $stmt = $mysqli->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        logError("Delete failed: Item not found. Table: $table, ID: $id");
        echo json_encode(['success' => false, 'error' => 'Item not found.']);
    }
} catch (Throwable $e) {
    logError('Delete item exception', $e);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
