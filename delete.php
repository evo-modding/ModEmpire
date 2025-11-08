<?php
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$uid = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Invalid request.'];
    header('Location: dashboard.php');
    exit;
}

$file_id = intval($_POST['id']);

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Find file and ensure ownership
    $stmt = $pdo->prepare("SELECT user_id, stored_name FROM files WHERE id = ? LIMIT 1");
    $stmt->execute([$file_id]);
    $f = $stmt->fetch();

    if (!$f || $f['user_id'] !== $uid) {
        $_SESSION['flash'] = ['type' => 'err', 'msg' => 'File not found or permission denied.'];
        $pdo->rollBack();
        header('Location: dashboard.php');
        exit;
    }

    // 1. Delete the physical file
    $path = PROTECTED_DIR . '/' . USER_DIR_PREFIX . '/' . $uid . '/' . $f['stored_name'];
    if (file_exists($path)) {
        @unlink($path); // Use @ to suppress warning if file is already gone
    }

    // 2. Delete download logs (optional, but good for cleanup)
    $stmt = $pdo->prepare("DELETE FROM download_logs WHERE file_id = ?");
    $stmt->execute([$file_id]);

    // 3. Delete file record from database
    $stmt = $pdo->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $uid]);

    // Commit changes
    $pdo->commit();

    $_SESSION['flash'] = ['type' => 'ok', 'msg' => 'File deleted successfully.'];

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash'] = ['type' => 'err', 'msg' => 'Error deleting file: ' . $e->getMessage()];
}

header('Location: dashboard.php');
exit;
