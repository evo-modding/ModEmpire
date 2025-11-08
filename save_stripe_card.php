<?php
require 'config.php';
require __DIR__ . '/../vendor/autoload.php';
session_start();

if (empty($_SESSION['user_id'])) { exit('Not logged in'); }

$uid = intval($_SESSION['user_id']);
$stripe = new \Stripe\StripeClient('');

$setup_intent_id = $_POST['setup_intent'] ?? null;
if(!$setup_intent_id) exit('Missing intent');

$intent = $stripe->setupIntents->retrieve($setup_intent_id, []);
$pm = $stripe->paymentMethods->retrieve($intent->payment_method, []);

$pdo->prepare("
  UPDATE payout_methods 
  SET method = 'stripe_credit', 
      stripe_card_brand = :brand, 
      stripe_card_last4 = :last4
  WHERE user_id = :uid
")->execute([
  ':brand' => $pm->card->brand,
  ':last4' => $pm->card->last4,
  ':uid' => $uid
]);

echo 'ok';
?>
