<?php
$title = 'Cash-Out Requests';
require 'header.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid = intval($_SESSION['user_id']);
$earning_rate = 0.00025;

// === Calculate new earnings since last cash-out ===
$stmt = $pdo->prepare("
  SELECT 
    COALESCE(SUM(f.downloads), 0) AS total_downloads,
    COALESCE(e.last_cashout_downloads, 0) AS last_cashout_downloads,
    GREATEST(COALESCE(SUM(f.downloads), 0) - COALESCE(e.last_cashout_downloads, 0), 0) AS new_downloads
  FROM files f
  LEFT JOIN earnings e ON f.user_id = e.user_id
  WHERE f.user_id = :uid
");
$stmt->execute([':uid' => $uid]);
$row = $stmt->fetch();

$total_downloads = (int)($row['total_downloads'] ?? 0);
$new_downloads   = (int)($row['new_downloads'] ?? 0);
$total_earnings  = round($new_downloads * $earning_rate, 5);

// === Payout info ===
$stmt = $pdo->prepare("SELECT * FROM payout_methods WHERE user_id = ?");
$stmt->execute([$uid]);
$payout = $stmt->fetch();

// === History ===
$stmt = $pdo->prepare("SELECT * FROM cashout_requests WHERE user_id = ? ORDER BY requested_at DESC");
$stmt->execute([$uid]);
$requests = $stmt->fetchAll();
$has_pending = array_filter($requests, fn($r)=>$r['status']==='pending');

// Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<h1>Cash-Out Requests</h1>

<?php if($flash): ?>
  <div class="<?= $flash['type'] === 'err' ? 'err' : 'flash' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>
<style>
    .badge {
        background-color: teal;
        border-radius: 10px;
        font-size: 18px;
        padding: 6px;
    }
</style>
<!-- Summary -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h3 class="card-title">Earnings Summary</h3>
    <a href="payout_settings.php" class="btn btn-secondary">Edit Payout Settings</a>
  </div>

  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;align-items:center;margin-top:10px;">
    <div>
      <p><strong>Total Downloads:</strong> <?= number_format($total_downloads) ?></p>
      <p><strong>Since Last Cash-Out:</strong> <?= number_format($new_downloads) ?></p>
      <p><strong>Rate per Download:</strong> $<?= number_format($earning_rate, 5) ?></p>
    </div>
    <div style="text-align:right;">
      <p style="font-size:1.5rem;margin:0;"><strong>Available Balance:</strong></p>
      <p style="font-size:2rem;color:#6ee7b7;font-weight:700;margin:0;">$<?= number_format($total_earnings, 5) ?></p>
    </div>
  </div>

  <div style="margin-top:15px;text-align:right;">
    <?php if($has_pending): ?>
      <button class="btn btn-secondary" disabled>Pending Request</button>
    <?php elseif($total_earnings > 0): ?>
      <button class="btn" onclick="toggleCashoutModal(true)">Request Cash-Out</button>
    <?php else: ?>
      <button class="btn btn-secondary" disabled>No Earnings Yet</button>
    <?php endif; ?>
  </div>
</div>

<!-- History -->
<div class="card" style="margin-top:20px;">
  <h3 class="card-title">Your Cash-Out History</h3>
  <?php if(empty($requests)): ?>
    <p class="small" style="margin-top:16px;">You haven’t made any cash-out requests yet.</p>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach($requests as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['requested_at']) ?></td>
            <td>$<?= number_format($r['amount'],5) ?></td>
            <td><?= ucfirst(str_replace('_',' ',$r['payout_method'])) ?></td>
            <td>
              <?php if($r['status']==='pending'): ?>
                <span class="badge" style="background:#facc15;color:#000;">Pending</span>
              <?php elseif($r['status']==='paid'): ?>
                <span class="badge" style="background:#6ee7b7;color:#000;">Paid</span>
              <?php elseif($r['status']==='declined'): ?>
                <span class="badge" style="background:#f87171;">Declined</span>
              <?php else: ?>
                <span class="badge"><?= htmlspecialchars($r['status']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Cash-Out Modal -->
<div id="cashoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:999;align-items:center;justify-content:center;">
  <div style="background:#0f172a;padding:30px;border-radius:10px;max-width:400px;width:100%;">
    <h3 style="margin-top:0;">Request Cash-Out</h3>

    <?php if(!$payout): ?>
      <p style="color:#f87171;">⚠ You must set up your payout method first.</p>
      <a href="payout_settings.php" class="btn btn-secondary" style="width:100%;">Go to Payout Settings</a>
    <?php else: ?>
      <form action="request_cashout.php" method="post">
        <p style="margin-bottom:10px;">Your current method:
          <strong><?= ucfirst(str_replace('_',' ',$payout['method'])) ?></strong>
          <?php if($payout['method']==='paypal' && $payout['paypal_email']): ?>
            <br><span class="small"><?= htmlspecialchars($payout['paypal_email']) ?></span>
          <?php elseif(str_contains($payout['method'],'stripe') && $payout['stripe_card_last4']): ?>
            <br><span class="small"><?= htmlspecialchars(strtoupper($payout['stripe_card_brand'])) ?> •••• <?= htmlspecialchars($payout['stripe_card_last4']) ?></span>
          <?php endif; ?>
        </p>
        <button type="submit" class="btn" style="width:100%;">Confirm Cash-Out</button>
        <button type="button" class="btn btn-secondary" onclick="toggleCashoutModal(false)" style="width:100%;margin-top:10px;">Cancel</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div style="text-align:center;margin-top:25px;">
  <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
</div>

<script>
function toggleCashoutModal(show){
  document.getElementById('cashoutModal').style.display = show ? 'flex' : 'none';
}
</script>

<?php require 'footer.php'; ?>
