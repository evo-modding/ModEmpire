<?php
require_once '../vendor/autoload.php';
use Stripe\Stripe;
use Stripe\Payout;

function payout_via_stripe($customer_id, $amount, $currency='usd'){
    if(!class_exists(\Stripe\Stripe::class)){
        return ['ok'=>false,'error'=>'Stripe SDK not installed (composer require stripe/stripe-php)'];
    }
    try{
        Stripe::setApiKey(STRIPE_SECRET_KEY);
        $amt_cents = (int)round($amount*100);
        // NOTE: Simple direct payout from platform balance to external account.
        // If you use Connect or need Transfers, adapt this to Transfers::create([...]).
        $p = Payout::create([
            'amount' => $amt_cents,
            'currency' => $currency,
            'description' => "Cashout to $customer_id"
        ]);
        return ['ok'=>true,'id'=>$p->id];
    }catch(\Exception $e){
        return ['ok'=>false,'error'=>$e->getMessage()];
    }
}

function payout_via_paypal($email, $amount, $currency='USD'){
    $base = PAYPAL_SANDBOX ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
    // Get token
    $ch = curl_init("$base/v1/oauth2/token");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_USERPWD=>PAYPAL_CLIENT_ID.':'.PAYPAL_SECRET,
        CURLOPT_POSTFIELDS=>"grant_type=client_credentials"
    ]);
    $resp = json_decode(curl_exec($ch), true); curl_close($ch);
    $token = $resp['access_token']??'';
    if(!$token) return ['ok'=>false,'error'=>'PayPal auth failed'];

    $payload = [
        "sender_batch_header" => [
            "sender_batch_id" => uniqid('batch_'),
            "email_subject" => "You have a payout from Evo Hosting"
        ],
        "items" => [[
            "recipient_type" => "EMAIL",
            "amount" => ["value"=>number_format($amount,2,'.',''), "currency"=>$currency],
            "receiver" => $email,
            "note" => "Your cash-out from Evo Hosting"
        ]]
    ];
    $ch = curl_init("$base/v1/payments/payouts");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER=>["Content-Type: application/json","Authorization: Bearer $token"],
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_RETURNTRANSFER=>true
    ]);
    $r = json_decode(curl_exec($ch), true); curl_close($ch);

    if(isset($r['batch_header']['payout_batch_id'])) return ['ok'=>true,'id'=>$r['batch_header']['payout_batch_id']];
    return ['ok'=>false,'error'=>$r['message']??'Unknown PayPal error'];
}
?>