<?php
require 'config.php';
$stmt = $pdo->query("SELECT original_name, downloads FROM files ORDER BY downloads DESC LIMIT 20");
require 'header.php';
?>
<h1>Top Files</h1>
<?php while($row = $stmt->fetch()): ?>
  <div class="card">
  <div><?= htmlspecialchars($row['original_name']) ?> — <?= $row['downloads'] ?> downloads</div>
  </div>
<?php endwhile; ?>
