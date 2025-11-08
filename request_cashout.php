<?php
require 'config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = intval($_SESSION['user_id']);

// === Prevent multiple pending requests ===
$stmt = $pdo->prepare("SELECT id FROM cashout_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
$stmt->execute([$uid]);
if ($stmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'You already have a pending cash-out request.'];
    header('Location: requests.php');
    exit;
}

// === Fetch current live totals ===
$stmt = $pdo->prepare("SELECT COALESCE(SUM(downloads),0) FROM files WHERE user_id = ?");
$stmt->execute([$uid]);
$current_total_downloads = (int)$stmt->fetchColumn();

// === Get last offset & calculate new earnings ===
$stmt = $pdo->prepare("SELECT last_cashout_downloads FROM earnings WHERE user_id = ?");
$stmt->execute([$uid]);
$offset = (int)($stmt->fetchColumn() ?? 0);

$new_downloads = max($current_total_downloads - $offset, 0);
$earning_rate = 0.00025;
$amount = round($new_downloads * $earning_rate, 5);

// === Enforce minimum payout ($2.50) ===
$MIN_CASHOUT = 2.50;
if ($amount < $MIN_CASHOUT) {
    $_SESSION['flash'] = [
        'type' => 'err',
        'msg'  => 'You must have at least $' . number_format($MIN_CASHOUT, 2) . ' in earnings before cashing out.'
    ];
    header('Location: requests.php');
    exit;
}

if ($amount <= 0) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'You have no new earnings to cash out.'];
    header('Location: requests.php');
    exit;
}

// === Verify payout method ===
$stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ?");
$stmt->execute([$uid]);
$payout = $stmt->fetch();

if (!$payout) {
    $_SESSION['flash'] = ['type'=>'err','msg'=>'Please set up your payout method first.'];
    header('Location: payout_settings.php');
    exit;
}

$payout_method = $payout['method'];
$payout_info = $payout['paypal_email'] ??
    (($payout['stripe_card_brand'] ?? '') . ' ••••' . ($payout['stripe_card_last4'] ?? ''));

// === Insert new request ===
$stmt = $pdo->prepare("
    INSERT INTO cashout_requests (user_id, amount, payout_method, total_downloads)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$uid, $amount, $payout_method, $current_total_downloads]);

// === Reset earnings offset ===
$pdo->prepare("
    UPDATE earnings
    SET total_earned = 0.00000, last_cashout_downloads = :tdl
    WHERE user_id = :uid
")->execute([
    ':tdl' => $current_total_downloads,
    ':uid' => $uid
]);

// === Notify admin ===
$to = "devlooxyz@gmail.com";
$subject = "New Cash-Out Request – User #{$uid}";
$message = "
User ID: {$uid}
Requested Amount: $" . number_format($amount, 5) . "
Payout Method: {$payout_method}
Payout Info: {$payout_info}
Total Downloads: {$current_total_downloads}
Time: " . date('Y-m-d H:i:s') . "
Dashboard: https://evohosting.cloud/cloud/admin_cashouts.php
";
$headers = "From: noreply@evohosting.cloud\r\n";
@mail($to, $subject, $message, $headers);

$_SESSION['flash'] = ['type'=>'ok','msg'=>'Cash-out request submitted successfully!'];
header('Location: requests.php');
exit;
?>
