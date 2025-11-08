<?php
require 'config.php';
require 'header.php';

if (empty($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$uid = intval($_SESSION['user_id']);
?>
<h1>Upgrade Plan</h1>

<style>
.plan-box {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 20px;
    margin-top: 18px;
    text-align: left;
}
.plan-box h2 { margin: 0 0 10px; }
.plan-box p { margin: 6px 0; opacity: .85; }
.plan-price { font-size: 1.3rem; margin-top: 8px; font-weight: bold; }
</style>

<p>Choose a plan and unlock more features instantly.</p>

<div class="plan-box">
    <h2>FREE Plan (Current)</h2>
    <p>• 3 uploads per day</p>
    <p>• Max file size: <strong>1 GB</strong></p>
    <p>• File retention: <strong>90 days</strong></p>
    <p>• Forced ads</p>
    <p class="plan-price">Cost: $0.00</p>
</div>

<div class="plan-box">
<form action="payment/upgrade_stripe.php" method="post">
    <h2>PRO Plan</h2>
    <p>• Upload up to <strong>10 files per day</strong></p>
    <p>• Upload larger files (up to <strong>5 GB</strong>)</p>
    <p>• <strong>No file expiration</strong> — files stay forever</p>
    <input type="hidden" name="plan" value="pro">
    <p class="plan-price">Price: <strong>$2.99</strong> (one-time)</p>
    <button class="btn" style="margin-top:10px;">Upgrade to PRO</button>
</form>
</div>

<div class="plan-box">
<form action="payment/upgrade_stripe.php" method="post">
    <h2>VIP Plan</h2>
    <p>• <strong>Unlimited uploads</strong> daily</p>
    <p>• Max upload size: <strong>20 GB</strong></p>
    <p>• <strong>No file expiration</strong> — files stay forever</p>
    <p>• <strong>No ads ever</strong> on any of your file pages</p>
    <p>• Faster downloads for your users</p>
    <input type="hidden" name="plan" value="vip">
    <p class="plan-price">Price: <strong>$9.99</strong> (one-time)</p>
    <button class="btn" style="margin-top:10px;">Upgrade to VIP</button>
</form>
</div>

<p style="margin-top:25px;">
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</p>

<?php require 'footer.php'; ?>
