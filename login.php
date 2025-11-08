<?php
require 'config.php';
$err='';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['user'] ?? '');
    $p = $_POST['password'] ?? '';
    if (!$u || !$p) $err='Fill both fields.';
    else {
        $stmt = $pdo->prepare("SELECT id,username,password_hash FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$u,$u]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($p, $row['password_hash'])) $err = 'Invalid credentials.';
        else {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            header('Location: dashboard.php'); exit;
        }
    }
}

$title = 'Login';
// Use new header, but minimal version (no nav)
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
<div class="form-box-wrapper">
  <div class="form-box">
    <h2>Login</h2>
    <form method="post">
      <label class="small" for="user">Username or email</label>
      <input id="user" name="user" required>
      <label class="small" for="pass">Password</label>
      <input id="pass" name="password" type="password" required>
      <button type="submit">Sign in</button>
    </form>
    <?php if($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <a class="small" href="register.php" style="text-align:center;">Create an account</a>
  </div>
</div>
</body>
</html>
