<?php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}
function verify_csrf(): void {
    $tok = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $tok)) { http_response_code(403); die('CSRF invalid'); }
}

function session_init(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.gc_maxlifetime', 7200);
    session_name('qslf');
    session_start();
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

function usuario_actual(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    if (empty($_SESSION['uid'])) return $cache = [];
    try {
        $st = get_pdo()->prepare("SELECT * FROM usuarios WHERE id=? AND activo=1");
        $st->execute([$_SESSION['uid']]);
        $cache = $st->fetch() ?: [];
    } catch (PDOException $e) { $cache = []; }
    return $cache;
}

function is_logged(): bool { return !empty($_SESSION['uid']); }
function is_admin(): bool  { return (usuario_actual()['rol'] ?? '') === 'admin'; }

function require_login(): void {
    if (!is_logged()) {
        header('Location: ' . APP_URL . '/account/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function gen_uuid(): string {
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function fmt_date_adif(string $adif_date): string {
    // ADIF: YYYYMMDD → d/m/Y
    if (strlen($adif_date) === 8) {
        return substr($adif_date, 6, 2) . '/' . substr($adif_date, 4, 2) . '/' . substr($adif_date, 0, 4);
    }
    return $adif_date;
}

function fmt_time_adif(string $t): string {
    // HHMMSS or HHMM → HH:MM
    if (strlen($t) >= 4) return substr($t, 0, 2) . ':' . substr($t, 2, 2);
    return $t;
}

function purge_old_output(): void {
    foreach (glob(OUTPUT_DIR . '*.zip') as $f) {
        if (time() - filemtime($f) > OUTPUT_TTL) @unlink($f);
    }
}

function allowed_fonts(): array {
    return [
        'roboto'        => 'Roboto Regular',
        'roboto-bold'   => 'Roboto Bold',
        'roboto-italic' => 'Roboto Italic',
        'courier'       => 'Courier Prime',
        'courier-bold'  => 'Courier Prime Bold',
        'opensans'      => 'Open Sans',
        'opensans-bold' => 'Open Sans Bold',
    ];
}

function font_path(string $key): string {
    $map = [
        'roboto'        => 'Roboto-Regular.ttf',
        'roboto-bold'   => 'Roboto-Bold.ttf',
        'roboto-italic' => 'Roboto-Italic.ttf',
        'courier'       => 'CourierPrime-Regular.ttf',
        'courier-bold'  => 'CourierPrime-Bold.ttf',
        'opensans'      => 'OpenSans-Regular.ttf',
        'opensans-bold' => 'OpenSans-Bold.ttf',
    ];
    $file = FONT_DIR . ($map[$key] ?? 'Roboto-Regular.ttf');
    return file_exists($file) ? $file : (FONT_DIR . 'Roboto-Regular.ttf');
}

function default_template(): array {
    return [
        'fields' => [
            'CALL'       => ['visible'=>true,  'x'=>540, 'y'=>180, 'size'=>52, 'color'=>'#ffffff', 'font'=>'roboto-bold',  'align'=>'center', 'prefix'=>''],
            'QSO_DATE'   => ['visible'=>true,  'x'=>100, 'y'=>300, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'Date: '],
            'TIME_ON'    => ['visible'=>true,  'x'=>350, 'y'=>300, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'UTC: '],
            'BAND'       => ['visible'=>true,  'x'=>100, 'y'=>350, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'Band: '],
            'MODE'       => ['visible'=>true,  'x'=>280, 'y'=>350, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'Mode: '],
            'RST_SENT'   => ['visible'=>true,  'x'=>460, 'y'=>350, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'RST: '],
            'RST_RCVD'   => ['visible'=>true,  'x'=>580, 'y'=>350, 'size'=>22, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'/'],
            'FREQ'       => ['visible'=>false, 'x'=>700, 'y'=>350, 'size'=>18, 'color'=>'#dddddd', 'font'=>'roboto',       'align'=>'left',   'prefix'=>''],
            'NAME'       => ['visible'=>false, 'x'=>100, 'y'=>400, 'size'=>20, 'color'=>'#ffffff', 'font'=>'roboto-italic','align'=>'left',   'prefix'=>''],
            'QTH'        => ['visible'=>false, 'x'=>100, 'y'=>430, 'size'=>18, 'color'=>'#dddddd', 'font'=>'roboto',       'align'=>'left',   'prefix'=>''],
            'GRIDSQUARE' => ['visible'=>false, 'x'=>100, 'y'=>460, 'size'=>18, 'color'=>'#dddddd', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'Grid: '],
            'OPERATOR'   => ['visible'=>false, 'x'=>100, 'y'=>490, 'size'=>18, 'color'=>'#dddddd', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'Op: '],
            'COMMENT'    => ['visible'=>false, 'x'=>100, 'y'=>520, 'size'=>16, 'color'=>'#cccccc', 'font'=>'roboto-italic','align'=>'left',   'prefix'=>''],
            'CUSTOM'     => ['visible'=>false, 'x'=>100, 'y'=>550, 'size'=>16, 'color'=>'#ffffff', 'font'=>'roboto',       'align'=>'left',   'prefix'=>'', 'custom_text'=>'Confirming our QSO — TNX!'],
        ],
    ];
}
