<?php
require_once __DIR__ . '/../config.php';
session_init();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($data['email'] ?? '');
$desc  = trim($data['desc']  ?? '');
$call  = strtoupper(preg_replace('/[^A-Za-z0-9\/]/', '', $data['call'] ?? ''));
$url   = trim($data['url']   ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($desc) < 10) {
    echo json_encode(['ok'=>false]); exit;
}

$subject = 'QSLforge bug report' . ($call ? " — $call" : '');
$body    = "Bug report recibido desde QSLforge\n";
$body   .= "=====================================\n\n";
if ($call)  $body .= "Indicativo: $call\n";
$body   .= "Email:      $email\n";
if ($url)   $body .= "URL:        $url\n";
$body   .= "\nDescripción:\n$desc\n";

$headers  = "From: QSLforge <web@mammoli.ar>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: QSLforge/" . APP_VERSION . "\r\n";

$ok = mail('web@mammoli.ar', $subject, $body, $headers);
echo json_encode(['ok' => $ok]);
