<?php
require 'config.php';
if (empty($_GET['token'])) {
    http_response_code(400);
    exit('Invalid request.');
}

$token = $_GET['token'];

// Decode token payload
list($payload_b64, $sig) = explode('.', $token, 2);
$payload = json_decode(base64_decode($payload_b64), true);

if (
    empty($payload['file_id']) ||
    empty($payload['stored']) ||
    empty($payload['user_id']) ||
    empty($payload['exp'])
) {
    http_response_code(400);
    exit('Invalid token.');
}

if (time() > intval($payload['exp'])) {
    http_response_code(403);
    exit('Token expired.');
}

$file_id = intval($payload['file_id']);
$user_id = intval($payload['user_id']);
$stored_name = basename($payload['stored']); // Prevent traversal

// Fetch file record
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
$stmt->execute([$file_id, $user_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File not found in DB.');
}

// Secure path
$path = __DIR__ . "/protected_files/users/{$user_id}/" . $stored_name;
if (!is_file($path)) {
    http_response_code(404);
    exit('File missing.');
}

// Update downloads
$pdo->prepare("UPDATE files SET downloads = downloads + 1 WHERE id = ?")->execute([$file_id]);

// === Earnings tracking ===
$earning_rate = 0.00025;
$stmt = $pdo->prepare("SELECT SUM(downloads) AS total FROM files WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_downloads = intval($stmt->fetchColumn());
$total_earned = round($total_downloads * $earning_rate, 5);
$pdo->prepare("
    INSERT INTO earnings (user_id, total_downloads, total_earned)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE
      total_downloads = VALUES(total_downloads),
      total_earned = VALUES(total_earned)
")->execute([$user_id, $total_downloads, $total_earned]);

// === File streaming section ===

// Clear any existing output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Disable compression
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', 'Off');

// Headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($path));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Stream file in chunks to avoid memory exhaustion
$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Failed to open file.');
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
    if (connection_status() != CONNECTION_NORMAL) {
        break;
    }
}
fclose($handle);
exit;
?>
