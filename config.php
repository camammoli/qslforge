<?php
ob_start();

define('APP_NAME',    'QSLforge');
define('APP_VERSION', '0.1.0');
define('APP_URL',     '/qslforge');
define('BASE_DIR',    __DIR__);

// Credenciales de BD en config.local.php (excluido del repo)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'qslforge');
    define('DB_USER', 'qslforge');
    define('DB_PASS', '');
}

define('FONT_DIR',    __DIR__ . '/assets/fonts/');

// Archivos temporales fuera del disco del hosting
$_qslf_tmp = rtrim(sys_get_temp_dir(), '/') . '/qslf_' . substr(md5(__DIR__), 0, 8) . '/';
define('UPLOAD_DIR', $_qslf_tmp . 'uploads/');
define('OUTPUT_DIR', $_qslf_tmp . 'output/');

define('MAX_ADIF_MB',  10);
define('MAX_IMG_MB',   20);
define('OUTPUT_TTL',   3600 * 4); // ZIPs se eliminan a las 4 horas
define('LOG_FILE',    $_qslf_tmp . 'security.log');

date_default_timezone_set('UTC');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/funciones.php';
