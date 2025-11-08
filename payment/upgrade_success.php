<?php
require '../config.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$uid = intval($_SESSION['user_id']);
$plan = $_GET['plan'] ?? null;

if (!$plan) die("Missing plan.");

// Update the user's plan
$stmt = $pdo->prepare("UPDATE users SET plan = ? WHERE id = ?");
$stmt->execute([$plan, $uid]);

$_SESSION['flash'] = ['type' => 'ok', 'msg' => '✅ Upgrade successful! You are now ' . strtoupper($plan) . '!'];
header("Location: ../dashboard.php");
exit;
