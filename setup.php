<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('APP_NAME',    'QSLforge');
define('APP_VERSION', '0.1.0');
define('APP_URL',     '/qslforge');
define('BASE_DIR',    __DIR__);
define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('OUTPUT_DIR',  __DIR__ . '/output/');
define('FONT_DIR',    __DIR__ . '/assets/fonts/');
define('OUTPUT_TTL',  3600 * 4);
define('MAX_ADIF_MB', 10);
define('MAX_IMG_MB',  20);

$key = $_GET['key'] ?? '';
if ($key !== 'qslforge_setup_2026') { http_response_code(403); die('Forbidden'); }

// ── DB config ─────────────────────────────────────────────────────────────────
$db_host = 'localhost';
$db_name = 'mammoli_qslforge';
$db_user = 'mammoli_carlos';
$db_pass = 'REDACTED_DB_PASS';

// ── Server checks ─────────────────────────────────────────────────────────────
$checks = [
    'PHP >= 8.0'            => version_compare(PHP_VERSION, '8.0.0', '>='),
    'GD extension'          => extension_loaded('gd'),
    'imagettftext (TTF)'    => function_exists('imagettftext'),
    'ZipArchive'            => class_exists('ZipArchive'),
    'PDO MySQL'             => in_array('mysql', PDO::getAvailableDrivers()),
    'mail() available'      => function_exists('mail'),
    'uploads/ exists'       => is_dir(UPLOAD_DIR),
    'uploads/ writable'     => is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR),
    'output/ exists'        => is_dir(OUTPUT_DIR),
    'output/ writable'      => is_dir(OUTPUT_DIR)  && is_writable(OUTPUT_DIR),
    'Roboto font present'   => file_exists(FONT_DIR . 'Roboto-Regular.ttf'),
];

// ── Create missing dirs ───────────────────────────────────────────────────────
foreach ([UPLOAD_DIR, OUTPUT_DIR] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

// ── DB check: connect WITHOUT dbname first ────────────────────────────────────
$db_status  = '';
$db_ok      = false;
$db_exists  = false;
$db_created = false;

try {
    $pdo_plain = new PDO(
        "mysql:host={$db_host};charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if DB exists
    $r = $pdo_plain->query("SHOW DATABASES LIKE '{$db_name}'")->fetchColumn();
    if ($r) {
        $db_exists = true;
        $db_status = "✓ Database '{$db_name}' found.";
    } else {
        // Try to create it
        try {
            $pdo_plain->exec("CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db_exists  = true;
            $db_created = true;
            $db_status  = "✓ Database '{$db_name}' created automatically.";
        } catch (\Throwable $ce) {
            $db_status = "✗ Database '{$db_name}' does not exist and could not be created automatically. Create it in cPanel first. Error: " . $ce->getMessage();
        }
    }
    $db_ok = $db_exists;
} catch (\Throwable $e) {
    $db_status = "✗ Cannot connect to MySQL: " . $e->getMessage();
}

// ── Migration SQLs ────────────────────────────────────────────────────────────
$sqls = [
    "CREATE TABLE IF NOT EXISTS `{$db_name}`.usuarios (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        callsign      VARCHAR(20)  NOT NULL,
        email         VARCHAR(200) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        nombre        VARCHAR(200) NULL,
        rol           ENUM('user','admin') NOT NULL DEFAULT 'user',
        activo        TINYINT(1) NOT NULL DEFAULT 1,
        lang          ENUM('en','es') NOT NULL DEFAULT 'en',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ultimo_login  DATETIME NULL
    )",

    "CREATE TABLE IF NOT EXISTS `{$db_name}`.templates (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT NOT NULL,
        nombre      VARCHAR(100) NOT NULL,
        config      JSON NOT NULL,
        is_default  TINYINT(1) NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id)
    )",

    "CREATE TABLE IF NOT EXISTS `{$db_name}`.lotes (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        uuid        VARCHAR(36)  NOT NULL UNIQUE,
        usuario_id  INT NULL,
        n_qsos      INT NOT NULL DEFAULT 0,
        n_errors    INT NOT NULL DEFAULT 0,
        zip_path    VARCHAR(500) NULL,
        n_emails    INT NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at  DATETIME NULL,
        INDEX idx_usuario (usuario_id),
        INDEX idx_uuid (uuid)
    )",
];

// ── Run migrations ────────────────────────────────────────────────────────────
$results = [];
$run = isset($_GET['run']) && $db_ok;

if ($run) {
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        foreach ($sqls as $sql) {
            try {
                $pdo->exec($sql);
                $results[] = ['ok' => true,  'sql' => _setup_short($sql)];
            } catch (\Throwable $e) {
                $results[] = ['ok' => false, 'sql' => _setup_short($sql), 'err' => $e->getMessage()];
            }
        }
    } catch (\Throwable $e) {
        $results[] = ['ok' => false, 'sql' => 'DB connection', 'err' => $e->getMessage()];
    }
}

function _setup_short(string $sql): string {
    preg_match('/CREATE TABLE.*?`(\w+)`/', $sql, $m);
    return $m[1] ?? trim(substr($sql, 0, 60)) . '…';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QSLforge — Setup</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:720px">
  <h4 class="fw-bold mb-1">
    <span style="color:#1a2a3a">&#9993;</span> QSLforge — Setup
  </h4>
  <p class="text-muted small mb-4">PHP <?= PHP_VERSION ?> · <?= php_uname('s') ?></p>

  <!-- DB Status -->
  <div class="alert <?= $db_ok ? 'alert-success' : 'alert-danger' ?> py-2 small">
    <i class="bi bi-database me-1"></i> <?= htmlspecialchars($db_status) ?>
  </div>

  <!-- Server checks -->
  <div class="card mb-4">
    <div class="card-header fw-semibold small">Server checks</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0 small">
        <?php foreach ($checks as $label => $ok): ?>
        <tr>
          <td class="ps-3"><?= htmlspecialchars($label) ?></td>
          <td class="<?= $ok ? 'text-success' : 'text-warning' ?>">
            <?= $ok ? '✓ OK' : '✗ Missing / not writable' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Migration results -->
  <?php if ($results): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold small">Migration results</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0 small">
        <?php foreach ($results as $r): ?>
        <tr>
          <td class="ps-3"><code><?= htmlspecialchars($r['sql']) ?></code></td>
          <td class="<?= $r['ok'] ? 'text-success' : 'text-danger' ?>">
            <?= $r['ok'] ? '✓ OK' : '✗ ' . htmlspecialchars($r['err'] ?? '') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php if (array_filter($results, fn($r) => $r['ok'])): ?>
  <div class="alert alert-success small">
    All done! <a href="<?= APP_URL ?>/">Go to QSLforge →</a>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Actions -->
  <?php if (!$db_ok): ?>
  <div class="alert alert-warning small">
    <strong>Before running migrations:</strong> create the database
    <code><?= htmlspecialchars($db_name) ?></code> in cPanel → MySQL Databases,
    then assign the user <code><?= htmlspecialchars($db_user) ?></code> with ALL PRIVILEGES.
    Then reload this page.
  </div>
  <?php else: ?>
  <form method="get">
    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
    <input type="hidden" name="run" value="1">
    <button class="btn btn-warning">Run migrations</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
