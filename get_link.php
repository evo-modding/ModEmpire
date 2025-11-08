<?php
require 'config.php';

// Get file ID from URL
$file_id = intval($_GET['id'] ?? 0);
if (!$file_id) {
    http_response_code(400);
    die('Invalid file ID.');
}

// Fetch file record
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? LIMIT 1");
$stmt->execute([$file_id]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    die('File not found.');
}

// --- Permission Check ---
$is_public = ($f['visibility'] === 'public');
$is_owner = (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $f['user_id']);

// Allow if it's public OR if the logged-in user is the owner
if (!$is_public && !$is_owner) {
    // If not public and not owner, check if user is logged in
    if (empty($_SESSION['user_id'])) {
        // Not logged in, ask them to log in
        $_SESSION['flash'] = ['type' => 'err', 'msg' => 'You must log in to access this file.'];
        header('Location: login.php');
        exit;
    } else {
        // Logged in, but it's not their private file
        http_response_code(403);
        die('Access Denied. This is a private file you do not own.');
    }
}

// --- Generate Secure Token ---
// All checks passed, user is allowed to download.
// Create a signed token for serve.php

$payload = [
    'file_id'   => $f['id'],
    'stored'    => $f['stored_name'], // The actual filename on disk
    'user_id'   => $f['user_id'],     // The owner's ID
    'exp'       => time() + TOKEN_TTL // Expiry time from config
];

$json_payload = json_encode($payload);
// Base64 encode, and make it URL-safe (replace +/ with -_)
$b64_payload = strtr(base64_encode($json_payload), '+/', '-_');

// Create the signature
$signature = hash_hmac('sha256', $b64_payload, DOWNLOAD_SECRET);

// Final token: payload.signature
$token = $b64_payload . '.' . $signature;

// Redirect to the serve script with the token
header('Location: serve.php?token=' . $token);
exit;
