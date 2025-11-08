<?php
require 'config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$file_id = intval($_GET['id'] ?? 0);

if (!$file_id) {
    http_response_code(400);
    exit('Invalid file ID.');
}

// Fetch file
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$is_owner = ($user_id === intval($file['user_id']));

// Path — use your protected directory
$path = __DIR__ . "/protected_files/users/{$file['user_id']}/" . basename($file['stored_name']);
if (!file_exists($path)) {
    http_response_code(404);
    exit('File missing from server.');
}

// Only increment downloads and earnings if:
// - The file is public
// - The downloader is not the owner
if ($file['visibility'] === 'public' && !$is_owner) {
    // Increment downloads
    $pdo->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?")->execute([$file_id]);

    // Earnings update
    $earning_rate = 0.00025;
    $stmt = $pdo->prepare("SELECT SUM(downloads) FROM files WHERE user_id = ?");
    $stmt->execute([$file['user_id']]);
    $total_downloads = intval($stmt->fetchColumn());
    $total_earned = round($total_downloads * $earning_rate, 6);

    $pdo->prepare("
        INSERT INTO earnings (user_id, total_downloads, total_earned)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          total_downloads = VALUES(total_downloads),
          total_earned = VALUES(total_earned)
    ")->execute([$file['user_id'], $total_downloads, $total_earned]);
}

// Stream file directly
if (ob_get_level()) ob_end_clean();
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
ini_set('zlib.output_compression', 'Off');

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($path));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Failed to open file.');
}
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
    if (connection_status() != CONNECTION_NORMAL) break;
}
fclose($handle);
exit;
?>
