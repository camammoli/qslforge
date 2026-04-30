<?php
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

require_once __DIR__ . '/includes/db.php';

$checks = [
    'GD extension'          => extension_loaded('gd'),
    'imagettftext (TTF)'    => function_exists('imagettftext'),
    'ZipArchive'            => class_exists('ZipArchive'),
    'mail() available'      => function_exists('mail'),
    'uploads/ writable'     => is_writable(UPLOAD_DIR),
    'output/ writable'      => is_writable(OUTPUT_DIR),
    'Roboto font present'   => file_exists(FONT_DIR . 'Roboto-Regular.ttf'),
];

$sqls = [
    "CREATE TABLE IF NOT EXISTS usuarios (
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

    "CREATE TABLE IF NOT EXISTS templates (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT NOT NULL,
        nombre      VARCHAR(100) NOT NULL,
        config      JSON NOT NULL,
        is_default  TINYINT(1) NOT NULL DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id)
    )",

    "CREATE TABLE IF NOT EXISTS lotes (
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

    "CREATE TABLE IF NOT EXISTS sesiones (
        id            VARCHAR(128) PRIMARY KEY,
        data          MEDIUMTEXT NOT NULL,
        last_activity INT UNSIGNED NOT NULL,
        usuario_id    INT NULL,
        INDEX idx_activity (last_activity)
    )",
];

$results = [];
$run = isset($_GET['run']);
if ($run) {
    try {
        $pdo = get_pdo();
        foreach ($sqls as $sql) {
            try {
                $pdo->exec($sql);
                $results[] = ['ok' => true, 'sql' => trim(substr($sql, 0, 60)) . '…'];
            } catch (PDOException $e) {
                $results[] = ['ok' => false, 'sql' => trim(substr($sql, 0, 60)) . '…', 'err' => $e->getMessage()];
            }
        }
    } catch (PDOException $e) {
        $results[] = ['ok' => false, 'sql' => 'DB connection', 'err' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QSLforge Setup</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:700px">
  <h4 class="fw-bold mb-4"><i class="bi bi-envelope-paper-heart me-2 text-warning"></i>QSLforge — Setup</h4>

  <div class="card mb-4">
    <div class="card-header fw-semibold">Server checks</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <?php foreach ($checks as $label => $ok): ?>
        <tr>
          <td class="ps-3"><?= htmlspecialchars($label) ?></td>
          <td><?= $ok ? '<span class="text-success">✓ OK</span>' : '<span class="text-danger">✗ Missing</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <?php if ($results): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold">Migration results</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <?php foreach ($results as $r): ?>
        <tr>
          <td class="ps-3 small"><code><?= htmlspecialchars($r['sql']) ?></code></td>
          <td><?= $r['ok'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">' . htmlspecialchars($r['err'] ?? '') . '</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <form method="get">
    <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
    <input type="hidden" name="run" value="1">
    <button class="btn btn-warning">Run migrations</button>
  </form>
</div>
</body>
</html>
