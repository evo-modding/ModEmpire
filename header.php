<?php
// This now handles the session and config for all pages
require_once 'config.php';
$base_url = base_url();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'Mod Empire') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="style.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrap">
  <header class="header">
    <a class="logo" href="index.php">Mod Empire</a>
    <nav>
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span class="user-greeting">Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a class="btn" href="dashboard.php">Dashboard</a>
        <a class="btn" href="requests.php">Cashout</a>
        <a class="btn btn-secondary" href="leaderboard.php">Leaderboard</a>
        <a class="btn btn-secondary" href="public.php">Public Files</a>
        <a class="btn btn-secondary" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="btn" href="public.php">Public Files</a>
        <a class="btn" href="login.php">Login</a>
        <a class="btn btn-secondary" href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </header>

  <main>
