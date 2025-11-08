<?php
require 'config.php';
$err='';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) $err='All fields required.';
    elseif (strlen($password) < 6) $err='Password must be at least 6 characters.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err='Invalid email.';
    else {
        // check duplicates
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) $err='Username or email already in use.';
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username,email,password_hash) VALUES (?,?,?)");
            $stmt->execute([$username,$email,$hash]);
            $uid = $pdo->lastInsertId();
            
            // create user folder
            $udir = PROTECTED_DIR . '/' . USER_DIR_PREFIX . '/' . intval($uid);
            @mkdir($udir, 0755, true); // @ is ok here as we can check on upload
            
            // login
            $_SESSION['user_id'] = $uid;
            $_SESSION['username'] = $username;
            header('Location: dashboard.php'); exit;
        }
    }
}

$title = 'Register';
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
    <h2>Create account</h2>
    <form method="post">
      <label class="small" for="user">Username</label>
      <input id="user" name="username" required>
      <label class="small" for="email">Email</label>
      <input id="email" name="email" type="email" required>
      <label class="small" for="pass">Password (min. 6 chars)</label>
      <input id="pass" name="password" type="password" required>
      <button type="submit">Register</button>
    </form>
    <?php if($err): ?><div class="err"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <a class="small" href="login.php" style="text-align:center;">Already registered? Login</a>
  </div>
</div>
</body>
</html>
