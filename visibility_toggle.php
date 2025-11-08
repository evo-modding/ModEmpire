<?php
require 'config.php';
session_start();

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$file_id = intval($_POST['id'] ?? 0);
$visibility = ($_POST['visibility'] ?? '') === 'public' ? 'public' : 'private';

$stmt = $pdo->prepare("UPDATE files SET visibility = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$visibility, $file_id, $_SESSION['user_id']]);

$_SESSION['flash'] = ['type'=>'flash', 'msg'=>'Visibility updated!'];
header('Location: dashboard.php');
exit;
