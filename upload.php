<?php
require 'config.php';

ini_set('upload_max_filesize', '20000M');
ini_set('post_max_size', '20000M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = intval($_SESSION['user_id']);
$today = date('Y-m-d');

// Load user info
$stmt = $pdo->prepare("SELECT plan, daily_uploads, last_upload_date FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Plan upload limits per day
$planLimits = [
    'free' => 3,
    'pro'  => 10,
    'vip'  => 999999
];
$limit = $planLimits[$user['plan']] ?? 3;

// Max file size based on plan
$planMaxSize = [
    'free' => 1000 * 1024 * 1024,
    'pro'  => 5 * 1024 * 1024 * 1024,
    'vip'  => 20 * 1024 * 1024 * 1024
];
$max_size = $planMaxSize[$user['plan']] ?? (200 * 1024 * 1024);

// Retention policy
$planRetention = [
    'free' => 90,
    'pro'  => 0,
    'vip'  => 0
];
$retention_days = $planRetention[$user['plan']] ?? 30;
$expires_at = ($retention_days > 0) ? date('Y-m-d', time() + ($retention_days * 86400)) : null;

// Reset counter if new day
if ($user['last_upload_date'] !== $today) {
    $stmt = $pdo->prepare("UPDATE users SET daily_uploads = 0, last_upload_date = ? WHERE id = ?");
    $stmt->execute([$today, $uid]);
    $user['daily_uploads'] = 0;
}

// Enforce daily limit
if ($user['daily_uploads'] >= $limit) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Daily upload limit for your plan reached.'];
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'No file uploaded.'];
    header('Location: dashboard.php');
    exit;
}

$up = $_FILES['file'];

if ($up['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Upload error.'];
    header('Location: dashboard.php');
    exit;
}

// Plan-based max file size
if ($up['size'] > $max_size) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Your plan allows max upload size of ' . round($max_size/1024/1024) . ' MB'];
    header('Location: dashboard.php');
    exit;
}

// Secure filename
$original_name = basename($up['name']);
$original_name = preg_replace('/[[:cntrl:]]/', '', $original_name);

$ext = pathinfo($original_name, PATHINFO_EXTENSION);
$stored_name = bin2hex(random_bytes(16)) . ($ext ? "." . preg_replace('/[^a-zA-Z0-9]/','',$ext) : "");

// Ensure directory exists
$udir = PROTECTED_DIR . '/' . USER_DIR_PREFIX . '/' . $uid;
if (!is_dir($udir)) mkdir($udir, 0755, true);

$target = $udir . '/' . $stored_name;

if (!move_uploaded_file($up['tmp_name'], $target)) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Failed to move uploaded file.'];
    header('Location: dashboard.php');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($target) ?: 'application/octet-stream';

$vis = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

$stmt = $pdo->prepare("INSERT INTO files (user_id, original_name, stored_name, size, mime, visibility, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
$stmt->execute([$uid, $original_name, $stored_name, $up['size'], $mime, $vis, $expires_at]);

$stmt = $pdo->prepare("UPDATE users SET daily_uploads = daily_uploads + 1, last_upload_date = ? WHERE id = ?");
$stmt->execute([$today, $uid]);

$_SESSION['flash'] = ['type' => 'ok', 'msg' => 'Upload successful: ' . htmlspecialchars($original_name)];
header('Location: dashboard.php');
exit;
