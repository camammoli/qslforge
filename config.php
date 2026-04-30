<?php
ob_start();

define('APP_NAME',    'QSLforge');
define('APP_VERSION', '0.1.0');
define('APP_URL',     '/qslforge');
define('BASE_DIR',    __DIR__);

define('DB_HOST', 'localhost');
define('DB_NAME', 'mammoli_qsl');
define('DB_USER', 'mammoli_carlos');
define('DB_PASS', 'REDACTED_DB_PASS');

define('UPLOAD_DIR',  __DIR__ . '/uploads/');
define('OUTPUT_DIR',  __DIR__ . '/output/');
define('FONT_DIR',    __DIR__ . '/assets/fonts/');

define('MAX_ADIF_MB',  10);
define('MAX_IMG_MB',   20);
define('OUTPUT_TTL',   3600 * 4); // ZIPs se eliminan a las 4 horas

date_default_timezone_set('UTC');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/funciones.php';
