<?php
require_once __DIR__ . '/config.php';
session_init();

$uuid = preg_replace('/[^a-f0-9\-]/', '', $_GET['uuid'] ?? '');
if (!$uuid) { http_response_code(400); die('Bad request'); }

// Find the zip
$zip_path = $_SESSION['gen_zip'] ?? null;
$zip_name = $_SESSION['gen_zip_name'] ?? 'QSL_cards.zip';

// If session doesn't have it, try to find by pattern (same session)
if (!$zip_path || !file_exists($zip_path)) {
    try {
        $st = get_pdo()->prepare("SELECT zip_path FROM lotes WHERE uuid=? AND expires_at > NOW()");
        $st->execute([$uuid]);
        $row = $st->fetch();
        if ($row) { $zip_path = $row['zip_path']; $zip_name = basename($zip_path); }
    } catch (PDOException $e) {}
}

if (!$zip_path || !file_exists($zip_path)) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">ZIP not found or expired. Please generate again.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zip_name) . '"');
header('Content-Length: ' . filesize($zip_path));
header('Cache-Control: no-cache');
readfile($zip_path);

// Borrar archivos temporales después de enviar
$_zip   = $zip_path;
$_cards = OUTPUT_DIR . session_id() . '/';
$_bg    = UPLOAD_DIR . session_id() . '/';
register_shutdown_function(function() use ($_zip, $_cards, $_bg) {
    @unlink($_zip);
    foreach ([$_cards, $_bg] as $dir) {
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '*') ?: []);
            @rmdir($dir);
        }
    }
});
