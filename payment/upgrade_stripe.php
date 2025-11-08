<?php
require '../config.php';
require '../vendor/autoload.php';

if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$uid = intval($_SESSION['user_id']);
$plan = $_POST['plan'] ?? null;

$prices = [
    'pro' => 299,   // $4.99
    'vip' => 999   // $14.99
];

if (!isset($prices[$plan])) {
    die("Invalid plan.");
}

$session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'success_url' => "https://modempire.evohosting.cloud/payment/upgrade_success.php?plan={$plan}",
    'cancel_url'  => "https://modempire.evohosting.cloud/upgrade.php",
    'line_items' => [[
        'price_data' => [
            'currency' => STRIPE_DEFAULT_CURRENCY,
            'product_data' => ['name' => strtoupper($plan) . " Plan Upgrade"],
            'unit_amount' => $prices[$plan],
        ],
        'quantity' => 1,
    ]],
]);

header("Location: " . $session->url);
exit;
