<?php
$title = 'Public Downloads';
require 'header.php'; // Use new header

// list public files with owner
$stmt = $pdo->query("SELECT f.*, u.username FROM files f JOIN users u ON u.id = f.user_id WHERE f.visibility='public' ORDER BY f.created_at DESC LIMIT 200");
$files = $stmt->fetchAll();
?>

<h1>Public Downloads</h1>
<p class="small" style="margin-top:-10px; margin-bottom: 20px;">All publicly shared files on Mod Empire.</p>

<div class="grid">
  <?php if (empty($files)): ?>
    <p>There are no public files available right now.</p>
  <?php else: ?>
    <?php foreach($files as $f): ?>
      <div class="card">
        <strong style="word-wrap: break-word;"><?=htmlspecialchars($f['original_name'])?></strong><br>
        <small class="small">
          by <?=htmlspecialchars($f['username'])?> • <?=number_format($f['size'] / 1024, 2)?> KB
        </small>
        <div style="margin-top:14px">
          <a class="btn" href="download.php?id=<?=intval($f['id'])?>">Download</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require 'footer.php'; // Use new footer ?>
