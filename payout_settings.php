<?php
require 'config.php';
require __DIR__ . '/../vendor/autoload.php';
require 'header.php'; 
session_start();

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = intval($_SESSION['user_id']);

\Stripe\Stripe::setApiKey('');

// --- Fetch payout record
$stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ?");
$stmt->execute([$uid]);
$pm = $stmt->fetch();

// --- Handle PayPal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['method'])) {
    $method = $_POST['method'] ?? 'paypal';
    if (!in_array($method, ['paypal','stripe_debit','stripe_credit'])) $method = 'paypal';

    $paypal_email = trim($_POST['paypal_email'] ?? null);

    if ($method === 'paypal' && !filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid PayPal email address.";
    } else {
        $pdo->prepare("
            INSERT INTO payout_methods (user_id, method, paypal_email)
            VALUES (:uid, :m, :p)
            ON DUPLICATE KEY UPDATE method = VALUES(method), paypal_email = VALUES(paypal_email)
        ")->execute([':uid'=>$uid, ':m'=>$method, ':p'=>$paypal_email]);
        header("Location: payout_settings.php?saved=1");
        exit;
    }
}

// --- Create or fetch Stripe customer
if ($pm && $pm['stripe_customer_id']) {
    $customer_id = $pm['stripe_customer_id'];
} else {
    $customer = \Stripe\Customer::create(['metadata' => ['user_id' => $uid]]);
    $customer_id = $customer->id;
    $pdo->prepare("
      INSERT INTO payout_methods (user_id, method, stripe_customer_id)
      VALUES (:uid, 'stripe_credit', :cid)
      ON DUPLICATE KEY UPDATE stripe_customer_id = VALUES(stripe_customer_id)
    ")->execute([':uid'=>$uid, ':cid'=>$customer_id]);
}

$intent = \Stripe\SetupIntent::create(['customer' => $customer_id]);
$stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ?");
$stmt->execute([$uid]);
$pm = $stmt->fetch();
?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h2>Payout Settings</h2>
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
  </div>

  <?php if(isset($_GET['saved'])): ?>
    <div style="color:#6ee7b7;">✅ Settings saved successfully.</div>
  <?php endif; ?>
  <?php if(!empty($error)): ?><p style="color:#f87171;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <!-- Current Info -->
  <?php if($pm): ?>
    <div class="info-box">
      <strong>Current Method:</strong> <?= ucfirst(str_replace('_', ' ', $pm['method'])) ?><br>
      <?php if($pm['method']==='paypal' && $pm['paypal_email']): ?>
        PayPal Email: <?= htmlspecialchars($pm['paypal_email']) ?>
      <?php elseif(str_contains($pm['method'],'stripe')): ?>
        <?php if($pm['stripe_card_last4']): ?>
          Card: <?= htmlspecialchars(strtoupper($pm['stripe_card_brand'])) ?> •••• <?= htmlspecialchars($pm['stripe_card_last4']) ?>
        <?php else: ?>
          No card on file yet.
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <p class="small">No payout information saved yet.</p>
  <?php endif; ?>

  <form method="post" id="paypal-form">
    <label><strong>Change Method:</strong></label>
    <select name="method" id="method" onchange="toggleMethod(this.value)">
      <option value="paypal" <?= ($pm['method'] ?? '')==='paypal'?'selected':'' ?>>PayPal</option>
      <option value="stripe_debit" <?= ($pm['method'] ?? '')==='stripe_debit'?'selected':'' ?>>Stripe Debit</option>
      <option value="stripe_credit" <?= ($pm['method'] ?? '')==='stripe_credit'?'selected':'' ?>>Stripe Credit</option>
    </select>

    <div id="paypal-fields" style="display:none;">
      <label>PayPal Email</label>
      <input type="email" name="paypal_email" value="<?= htmlspecialchars($pm['paypal_email'] ?? '') ?>" placeholder="you@example.com">
      <button type="submit" class="btn">Save PayPal</button>
    </div>
  </form>

  <!-- Stripe Card Section -->
  <div id="stripe-fields" style="display:none;">
    <label>Add / Update Card</label>
    <div id="card-element" style="padding:10px;background:white;border-radius:6px;"></div>
    <button id="card-save" class="btn" style="margin-top:10px;">Save Card</button>
  </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe("");
const elements = stripe.elements();
const cardElement = elements.create("card");
cardElement.mount("#card-element");

document.getElementById('card-save').addEventListener('click', async (e)=>{
  e.preventDefault();
  const { setupIntent, error } = await stripe.confirmCardSetup("<?= $intent->client_secret ?>", {
    payment_method: { card: cardElement }
  });
  if(error){
    alert(error.message);
  } else {
    fetch("save_stripe_card.php", {
      method: "POST",
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: "setup_intent="+setupIntent.id
    }).then(()=>location.reload());
  }
});

function toggleMethod(v){
  document.getElementById('paypal-fields').style.display = (v==='paypal') ? 'block' : 'none';
  document.getElementById('stripe-fields').style.display = (v.startsWith('stripe')) ? 'block' : 'none';
}
toggleMethod(document.getElementById('method').value);
</script>
