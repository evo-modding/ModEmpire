<?php
require 'config.php';
session_start();

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$file_id = intval($_POST['id'] ?? 0);
$new_name = trim($_POST['new_name'] ?? '');

if (!$file_id || $new_name === '') {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'Invalid rename request.'];
    header('Location: dashboard.php');
    exit;
}

// Validate length & remove path attempts
$new_name = basename($new_name);
if (strlen($new_name) > 255) $new_name = substr($new_name, 0, 255);

$stmt = $pdo->prepare("UPDATE files SET original_name = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$new_name, $file_id, $_SESSION['user_id']]);

$_SESSION['flash'] = ['type'=>'flash','msg'=>'Filename updated successfully!'];
header('Location: dashboard.php');
exit;
