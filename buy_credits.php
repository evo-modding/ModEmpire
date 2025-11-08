<?php
$title = "Buy Credits";
require 'header.php';
if (empty($_SESSION['user_id'])) { header("Location: login.php"); exit; }
?>

<h2>Buy Skip-Wait Credits</h2>
<p>Credits allow you to instantly download files without ads or waiting.</p>

<form action="payment/buy_credits_stripe.php" method="post">
    <input type="hidden" name="pack" value="small">
    <button class="btn">Buy 10 Credits — $1.99</button>
</form>

<form action="payment/buy_credits_stripe.php" method="post" style="margin-top:10px;">
    <input type="hidden" name="pack" value="medium">
    <button class="btn">Buy 25 Credits — $3.99</button>
</form>

<form action="payment/buy_credits_stripe.php" method="post" style="margin-top:10px;">
    <input type="hidden" name="pack" value="big">
    <button class="btn">Buy 50 Credits — $9.99</button>
</form>

<p style="margin-top:20px;">
    <a href="dashboard.php" class="btn btn-secondary">Back</a>
</p>
