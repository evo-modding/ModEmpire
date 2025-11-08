<?php
require 'config.php';
if (empty($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$uid = intval($_SESSION['user_id']);
$file_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$uid]);
$credits = $stmt->fetchColumn();

if ($credits <= 0) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'Not enough credits.'];
    header("Location: buy_credits.php");
    exit;
}

// Deduct credit
$pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id = ?")
    ->execute([$uid]);

// Send download immediately
header("Location: get_link.php?id={$file_id}");
exit;
