<?php
$title = 'Dashboard';
require 'header.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid = intval($_SESSION['user_id']);

// Fetch user info (plan + credits + upload counters)
$stmt = $pdo->prepare("SELECT username, plan, credits, daily_uploads, last_upload_date FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Reset upload counter if new day
$today = date('Y-m-d');
if ($user['last_upload_date'] !== $today) {
    $pdo->prepare("UPDATE users SET daily_uploads = 0, last_upload_date = ? WHERE id = ?")
        ->execute([$today, $uid]);
    $user['daily_uploads'] = 0;
}

$uploads_left = max(0, $user['plan'] === 'vip' ? 9999 : 3 - $user['daily_uploads']);
$limit_reached = ($uploads_left <= 0);

// Ensure credits never errors
$user['credits'] = $user['credits'] ?? 0;

// Fetch user files
$stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$uid]);
$files = $stmt->fetchAll();

// Flash messages
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>



<style>
/* Top row rename + visibility */
.file-row-top {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-row-top input,
.file-row-top select {
  font-size: 14px;
  color: #fff;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.25);
  border-radius: 6px;
  padding: 8px 10px;
  height: 38px;          /* ✅ same exact height */
  box-sizing: border-box;
}

.file-row-top input {
  flex: 1; /* ✅ rename input takes remaining space */
  min-width: 100%;
}

.file-row-top select {
  width: 90px; /* ✅ dropdown consistent width */
}

/* Bottom row action buttons */
.file-actions {
  display: flex;
  justify-content: flex-start;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}

.file-actions .btn,
.file-actions form button {
  flex: 0 0 auto;
  padding: 8px 14px;
  white-space: nowrap;
}



/* Upload progress bar */
#upload-progress { display:none; margin-top:12px; }
#upload-progress-bar {
  height:14px;
  width:0%;
  background:#6ee7b7;
  transition:width .25s;
}

/* One card per row */
.file-card {
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 14px;
  padding: 18px 20px;
  backdrop-filter: blur(12px);
  margin-top: 14px;
  transition: .2s;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.file-card:hover {
  background: rgba(255,255,255,0.10);
  border-color: rgba(255,255,255,0.25);
}

/* Title + meta */
.file-name { font-weight: 600; word-break: break-word; }
.file-meta { font-size: 13px; opacity: .75; }

/* Buttons row */
.file-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.file-actions form,
.file-actions a,
.file-actions button,
.file-actions select {
  flex: 1;
  min-width: 110px;
  white-space: nowrap;
}
.inline-form { display:inline; }

/* Make select match visually */
.file-actions select {
  background: rgba(255,255,255,0.1);
  border-radius: 6px;
  padding: 6px;
  border: 1px solid rgba(255,255,255,0.25);
  color: #fff;
}
</style>

<h1>Dashboard</h1>
<?php if($user['plan'] !== 'vip'): ?>
   <script async="async" data-cfasync="false" src="//pl28001966.effectivegatecpm.com/ba732e847660186e5c33ac968c9d0908/invoke.js"></script>
<div id="container-ba732e847660186e5c33ac968c9d0908"></div>
<script type='text/javascript' src='//pl28001975.effectivegatecpm.com/42/5d/a5/425da58d8154c92a40086f5fb2ddd8cb.js'></script>
<script>(function(s){s.dataset.zone='10152272',s.src='https://groleegni.net/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10152352',s.src='https://al5sm.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<?php endif; ?>

<?php if($flash): ?>
  <div class="<?= $flash['type'] === 'err' ? 'err' : 'flash' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <h2>Welcome, <?= htmlspecialchars($user['username']) ?>!</h2>

    <p>Plan: <strong><?= strtoupper($user['plan']) ?></strong></p>

    <p>Your Credits: <strong><?= $user['credits'] ?></strong></p>
    <a href="buy_credits.php" class="btn btn-secondary">Buy Credits</a>

    <?php if($user['plan'] !== 'vip'): ?>
        <a href="upgrade.php" class="btn" style="margin-left:10px;">Upgrade Plan</a>
    <?php endif; ?>

    <p style="margin-top:14px;">Uploads Remaining Today: <strong><?= $uploads_left ?></strong></p>
    <?php
$plan_max_upload = [
  'free' => 1000 * 1024 * 1024,
  'pro'  => 5 * 1024 * 1024 * 1024,
  'vip'  => 20 * 1024 * 1024 * 1024
];
$max_upload_display = round(($plan_max_upload[$user['plan']] ?? 1000*1024*1024) / 1024 / 1024) . " MB";

$plan_retention = [
  'free' => 90,
  'pro'  => 0,
  'vip'  => 0
];
$retention_display = ($plan_retention[$user['plan']] == 0) ? "Unlimited" : $plan_retention[$user['plan']] . " days";
?>

<p>Max Upload Size: <strong><?= $max_upload_display ?></strong></p>
<p>File Retention: <strong><?= $retention_display ?></strong></p>

</div>

<div class="files-container">


<div class="card">
  <h3>Upload New File</h3>

  <?php if($limit_reached): ?>
      <div class="err" style="margin-top:12px;">Daily upload limit reached. Resets at midnight.</div>
  <?php else: ?>
      <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
        <input type="file" name="file" id="file-upload" required style="display:none;">
        <label for="file-upload" class="btn btn-secondary">Choose File</label>
        <span id="file-upload-filename" class="small" style="margin-left:6px;">No file chosen</span>

        <select name="visibility" style="margin-top:0px;">
          <option value="public">Public</option>
          <option value="private" selected>Private</option>
        </select>

        <button type="submit" class="btn" id="upload-btn" style="margin-top:0px;">Upload</button>
      </form>
  <?php endif; ?>
</div>

<!-- User Files -->
<div class="card" style="margin-top:22px;">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3>Your Files</h3>
    <a href="public.php" class="btn btn-secondary">View Public Files</a>
  </div>

<?php if(empty($files)): ?>
  <p>No files uploaded yet.</p>

<?php else: ?>
  <?php foreach($files as $f):
    $id = intval($f['id']);
    $public_url = $base_url . "/download.php?id=" . $id;
  ?>
 <div class="file-card">

  <div class="file-meta">
    <?= number_format($f['size']/1024,2) ?> KB • <?= $f['visibility']==='public'?'🌍 Public':'🔒 Private' ?> • Downloads: <?= intval($f['downloads']) ?>
  </div>

  <div class="file-row-top">
    <form action="rename.php" method="post" class="inline-form" style="flex:1;">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="text" name="new_name" value="<?= htmlspecialchars($f['original_name']) ?>">
    </form>

    <form action="visibility_toggle.php" method="post" style="flex:0 0 140px;">
      <input type="hidden" name="id" value="<?= $id ?>">
      <select name="visibility" onchange="this.form.submit()">
        <option value="public" <?= $f['visibility']==='public'?'selected':'' ?>>Public</option>
        <option value="private" <?= $f['visibility']==='private'?'selected':'' ?>>Private</option>
      </select>
    </form>
  </div>

  <div class="file-actions" style="margin-top:8px;">
    <button class="btn" onclick="this.parentElement.previousElementSibling.querySelector('form').submit()">Save Name</button>
    <a class="btn" href="download_direct.php?id=<?= $id ?>">Download</a>

    <?php if($f['visibility']==='public'): ?>
    <button class="btn btn-copy" data-url="<?= htmlspecialchars($public_url) ?>">Copy Link</button>
    <?php endif; ?>

    <form action="delete.php" method="post" style="flex:1;">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-danger" style="width:100%;">Delete</button>
    </form>
  </div>

</div>
  <?php endforeach; ?>
<?php endif; ?>

</div>
</div>

<script>
document.getElementById('file-upload').addEventListener('change', e =>
  document.getElementById('file-upload-filename').textContent = e.target.files[0].name
);
</script>


<?php require 'footer.php'; ?>