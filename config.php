<?php
// config.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lost_and_found');

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}

date_default_timezone_set('Africa/Nairobi');

// ── Error helpers ────────────────────────────────────────────────────────────

/**
 * Appends a timestamped entry to the error log and returns a generic
 * safe message suitable for sending back to the client.
 */
function logError(string $message, ?Throwable $exception = null): string
{
    $logFile   = __DIR__ . '/../error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $entry     = "[{$timestamp}] {$message}";

    if ($exception !== null) {
        $entry .= ' | ' . $exception->getMessage();
    }

    file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);

    return 'An internal error occurred. Please try again later.';
}

function jsonError(string $msg): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg]);
    exit();
}

function jsonSuccess(array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

// Catch any unhandled exception so it never leaks a stack trace to the client
set_exception_handler(function (Throwable $e): void {
    logError('Uncaught exception', $e);
    jsonError('Server error. Please try again later.');
});
