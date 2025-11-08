<?php
require '../config.php';
require '../vendor/autoload.php';

if (empty($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$packs = [
    'small'  => ['credits' => 10,  'amount' => 199],
    'medium' => ['credits' => 25,  'amount' => 399],
    'big'    => ['credits' => 50, 'amount' => 799],
];

$pack = $_POST['pack'] ?? null;
if (!isset($packs[$pack])) die("Invalid pack");

$credits = $packs[$pack]['credits'];
$amount  = $packs[$pack]['amount'];

$session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'success_url' => "https://modempire.evohosting.cloud/payment/buy_credits_success.php?c={$credits}",
    'cancel_url'  => "https://modempire.evohosting.cloud/buy_credits.php",
    'line_items' => [[
        'price_data' => [
            'currency' => STRIPE_DEFAULT_CURRENCY,
            'product_data' => ['name' => "{$credits} Download Credits"],
            'unit_amount' => $amount,
        ],
        'quantity' => 1,
    ]],
]);

header("Location: " . $session->url);
exit;
