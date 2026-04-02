<?php
session_start();
require_once 'backend/config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit();
}

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

$like  = '%' . $query . '%';
$rows  = [];

$stmt = $mysqli->prepare(
    "SELECT item_name, description, 'lost' AS type FROM lost_items WHERE item_name LIKE ?
     UNION
     SELECT item_name, description, 'found' AS type FROM found_items WHERE item_name LIKE ?"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results | Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .search-wrap { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .search-wrap h2 { color: #0a3d62; margin-bottom: 20px; }
        .result-card { background: #fff; border-radius: 10px; padding: 16px 20px; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .result-card h3 { color: #0a3d62; margin-bottom: 6px; font-size: 16px; }
        .result-card p { color: #555; font-size: 14px; }
        .badge { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:8px; }
        .badge.lost  { background:#fff3e0; color:#e65100; }
        .badge.found { background:#e8f5e9; color:#2e7d32; }
        .empty { text-align:center; color:#888; padding:50px 20px; font-size:15px; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="logo">Lost & Found</div>
    <ul class="nav-links">
        <li><a href="backend/dashboard.php">Dashboard</a></li>
        <li><a href="items.html">My Items</a></li>
        <li><a href="backend/logout.php">Logout</a></li>
    </ul>
</nav>

<div class="search-wrap">
    <h2>Results for: "<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"</h2>

    <?php if (empty($rows)): ?>
        <div class="empty">No items found matching your search.</div>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <div class="result-card">
                <span class="badge <?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(ucfirst($row['type']), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <h3><?php echo htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars($row['description'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p style="margin-top:20px;"><a href="index.html" style="color:#0a3d62;">← Back Home</a></p>
</div>
</body>
</html>