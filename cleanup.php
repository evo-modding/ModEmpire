<?php
require 'config.php';

// Delete expired files (not VIP)
$stmt = $pdo->query("SELECT id, user_id, stored_name FROM files WHERE expires_at IS NOT NULL AND expires_at < CURDATE()");
$files = $stmt->fetchAll();

foreach ($files as $f) {
    $path = PROTECTED_DIR . '/' . USER_DIR_PREFIX . '/' . $f['user_id'] . '/' . $f['stored_name'];
    if (file_exists($path)) unlink($path);
    $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$f['id']]);
}
