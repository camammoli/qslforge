<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/adif.php';
require_once __DIR__ . '/../includes/card_gen.php';
session_init();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
if (empty($_SESSION['gen_qsos']) || empty($_SESSION['gen_bg'])) { echo json_encode(['ok'=>false,'error'=>t('err_session')]); exit; }

$qso  = $_SESSION['gen_qsos'][0];
$bg   = $_SESSION['gen_bg'];

// Rebuild template from POST
$template = $_SESSION['gen_template'] ?? default_template();
$fields   = array_keys($template['fields']);
foreach ($fields as $f) {
    $template['fields'][$f]['visible'] = !empty($_POST['vis_' . $f]);
    $template['fields'][$f]['x']       = (int)($_POST['x_' . $f]      ?? $template['fields'][$f]['x']);
    $template['fields'][$f]['y']       = (int)($_POST['y_' . $f]      ?? $template['fields'][$f]['y']);
    $template['fields'][$f]['size']    = max(8, min(200, (int)($_POST['size_' . $f] ?? $template['fields'][$f]['size'])));
    $c = $_POST['color_' . $f] ?? '';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) $template['fields'][$f]['color'] = $c;
    $fk = $_POST['font_' . $f] ?? '';
    if (array_key_exists($fk, allowed_fonts())) $template['fields'][$f]['font'] = $fk;
    $al = $_POST['align_' . $f] ?? '';
    if (in_array($al, ['left','center','right'])) $template['fields'][$f]['align'] = $al;
    $template['fields'][$f]['prefix'] = substr($_POST['prefix_' . $f] ?? ($template['fields'][$f]['prefix'] ?? ''), 0, 50);
    if ($f === 'CUSTOM') $template['fields'][$f]['custom_text'] = substr($_POST['custom_text'] ?? ($template['fields'][$f]['custom_text'] ?? ''), 0, 200);
}

$out = generate_preview($bg, $qso, $template);
if (!$out) { echo json_encode(['ok'=>false,'error'=>t('err_generate')]); exit; }

echo json_encode([
    'ok'  => true,
    'url' => APP_URL . '/output/' . basename($out),
]);
