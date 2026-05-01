<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/adif.php';
require_once __DIR__ . '/../includes/card_gen.php';
require_once __DIR__ . '/../includes/mailer.php';
session_init();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), $tok)) { echo json_encode(['ok'=>false,'error'=>'CSRF invalid']); exit; }

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

if (empty($_SESSION['gen_qsos']) || empty($_SESSION['gen_bg'])) {
    echo json_encode(['ok'=>false,'error'=>t('err_session')]); exit;
}

$qsos     = $_SESSION['gen_qsos'];
$bg       = $_SESSION['gen_bg'];
$template = $_SESSION['gen_template'] ?? default_template();

// ── Generate batch ────────────────────────────────────────────────────────────
if ($action === 'generate') {
    // Filtrar QSOs seleccionados por el usuario (si se envía la lista)
    $allowed_calls = $body['calls'] ?? null;
    if ($allowed_calls !== null && is_array($allowed_calls) && count($allowed_calls)) {
        $allowed = array_map('strtoupper', $allowed_calls);
        $qsos = array_values(array_filter($qsos, fn($q) => in_array(strtoupper($q['CALL'] ?? ''), $allowed)));
    }
    if (empty($qsos)) { echo json_encode(['ok'=>false,'error'=>t('err_generate')]); exit; }

    purge_old_output();
    $result = generate_batch(session_id(), $bg, $qsos, $template);
    if (empty($result['files'])) { echo json_encode(['ok'=>false,'error'=>t('err_generate')]); exit; }

    $uuid     = gen_uuid();
    $zip_name = 'QSL_' . preg_replace('/[^A-Z0-9]/', '', strtoupper(session_id())) . '.zip';
    $zip_path = OUTPUT_DIR . $zip_name;

    if (!build_zip($result['files'], $zip_path)) { echo json_encode(['ok'=>false,'error'=>t('err_generate')]); exit; }

    $_SESSION['gen_uuid']     = $uuid;
    $_SESSION['gen_zip']      = $zip_path;
    $_SESSION['gen_files']    = $result['files'];
    $_SESSION['gen_zip_name'] = $zip_name;

    // Log to DB if possible
    try {
        $pdo = get_pdo();
        $pdo->prepare("INSERT INTO lotes (uuid, usuario_id, n_qsos, n_errors, zip_path, expires_at) VALUES (?,?,?,?,?,?)")
            ->execute([$uuid, $_SESSION['uid'] ?? null, count($result['files']), count($result['errors']), $zip_path, date('Y-m-d H:i:s', time() + OUTPUT_TTL)]);
    } catch (PDOException $e) {}

    echo json_encode(['ok'=>true, 'uuid'=>$uuid, 'n'=>count($result['files']), 'errors'=>count($result['errors'])]);

// ── Send emails ───────────────────────────────────────────────────────────────
} elseif ($action === 'send_emails') {
    $uuid     = $body['uuid']     ?? '';
    $from     = trim($body['from']     ?? '');
    $subject  = trim($body['subject']  ?? '');
    $body_tpl = trim($body['body_tpl'] ?? '');
    $dests    = $body['dests']    ?? [];

    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>t('err_email')]); exit; }
    if (empty($_SESSION['gen_files']))              { echo json_encode(['ok'=>false,'error'=>t('err_session')]); exit; }

    // Agrupar todos los archivos por indicativo (puede haber múltiples QSOs del mismo OM)
    $files_by_call = [];
    foreach ($_SESSION['gen_files'] as $f) {
        $call = strtoupper($f['call']);
        $files_by_call[$call][] = $f;
    }

    $yo       = usuario_actual();
    $callsign = $yo['callsign'] ?? 'N0CALL';
    $sent     = 0;
    $failed   = [];

    foreach ($dests as $d) {
        $call  = strtoupper(trim($d['call'] ?? ''));
        $email = trim($d['email'] ?? '');
        $name  = trim($d['name'] ?? $call);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
        if (!isset($files_by_call[$call])) continue;

        $callFiles = $files_by_call[$call];
        $qso = $callFiles[0]['qso']; // primer QSO para el cuerpo del email
        $vars = [
            '{name}'     => $name,
            '{date}'     => fmt_date_adif($qso['QSO_DATE'] ?? ''),
            '{band}'     => $qso['BAND'] ?? '',
            '{mode}'     => $qso['MODE'] ?? '',
            '{callsign}' => $callsign,
        ];
        $body_text = $body_tpl ? strtr($body_tpl, $vars) : t('email_body_def', [
            'name'     => $name,
            'date'     => fmt_date_adif($qso['QSO_DATE'] ?? ''),
            'band'     => $qso['BAND'] ?? '',
            'mode'     => $qso['MODE'] ?? '',
            'callsign' => $callsign,
        ]);
        // Un solo email con todas las tarjetas de ese indicativo adjuntas
        $attachments = array_map(fn($f) => ['path' => $f['path'], 'name' => $f['name']], $callFiles);
        $ok = send_qsl_email_multi($email, $from, $subject, $body_text, $attachments);
        if ($ok) $sent++; else $failed[] = $call;
    }

    // Update DB
    try {
        get_pdo()->prepare("UPDATE lotes SET n_emails=? WHERE uuid=?")
            ->execute([$sent, $body['uuid'] ?? '']);
    } catch (PDOException $e) {}

    $msg = t('email_done', ['n' => $sent]);
    if ($failed) $msg .= ' ' . t('email_errors', ['n' => count($failed)]);

    echo json_encode(['ok'=>true, 'sent'=>$sent, 'failed'=>$failed, 'message'=>$msg]);

} else {
    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
}
