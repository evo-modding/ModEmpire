<?php
// config.php
session_start();

// -- Database (edit) --
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// -- Paths --
define('PROTECTED_DIR', __DIR__ . '/protected_files');
define('USER_DIR_PREFIX', 'users'); // so per-user path: PROTECTED_DIR/users/{id}

// -- Security & Limits --
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// !! CRITICAL: YOU MUST CHANGE THIS TO A LONG, RANDOM, SECRET STRING !!
// !! Use a password generator. 64+ characters is good.               !!
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
define('DOWNLOAD_SECRET', '');
define('TOKEN_TTL', 90); // seconds
define('MAX_UPLOAD_BYTES', 1000 * 1024 * 1024); 
define('STRIPE_SECRET_KEY', '');
define('STRIPE_PUBLIC_KEY', '');  // optional (for front-end usage)
define('STRIPE_DEFAULT_CURRENCY', 'usd');

define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_SECRET', '');
define('PAYPAL_SANDBOX', true);
define('PAYPAL_CURRENCY', 'USD');


// PDO
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}

// helpers
function base_url() {
    $s = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Use rtrim on SCRIPT_NAME's dirname to get the base directory
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $s . '://' . $host . $dir;
}

// ensure protected dir exists and is writable
if (!is_dir(PROTECTED_DIR)) {
    if (!mkdir(PROTECTED_DIR, 0755, true)) {
        die('Failed to create protected directory. Check permissions: ' . PROTECTED_DIR);
    }
}
if (!is_writable(PROTECTED_DIR)) {
    die('Protected directory is not writable. Check permissions: ' . PROTECTED_DIR);
}

