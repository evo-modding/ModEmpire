<?php
require '../config.php';
if (empty($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$uid = intval($_SESSION['user_id']);
$credits = intval($_GET['c'] ?? 0);

if ($credits > 0) {
    $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
        ->execute([$credits, $uid]);
}

$_SESSION['flash'] = ['type'=>'ok','msg'=>"✅ Added $credits credits to your account!"];
header("Location: ../dashboard.php");
exit;
