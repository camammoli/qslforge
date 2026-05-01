<?php
function send_qsl_email(string $to, string $from, string $subject, string $body, string $attachment_path, string $attachment_name): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL))   return false;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    if (!file_exists($attachment_path))            return false;

    $boundary = '----QSLforge_' . md5(uniqid());
    $content  = file_get_contents($attachment_path);
    $encoded  = chunk_split(base64_encode($content));
    $mime     = str_ends_with(strtolower($attachment_path), '.png') ? 'image/png' : 'image/jpeg';

    $body_plain = wordwrap($body, 75, "\r\n");

    $headers  = "From: QSLforge <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: QSLforge/" . APP_VERSION . "\r\n";

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body_plain . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: {$mime}; name=\"{$attachment_name}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$attachment_name}\"\r\n\r\n";
    $message .= $encoded . "\r\n";
    $message .= "--{$boundary}--";

    return mail($to, $subject, $message, $headers);
}

// Envía un email con múltiples adjuntos (un email por indicativo, todas sus tarjetas)
function send_qsl_email_multi(string $to, string $from, string $subject, string $body, array $attachments): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL))   return false;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    $attachments = array_filter($attachments, fn($a) => file_exists($a['path']));
    if (empty($attachments)) return false;

    $boundary   = '----QSLforge_' . md5(uniqid());
    $body_plain = wordwrap($body, 75, "\r\n");

    $headers  = "From: QSLforge <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: QSLforge/" . APP_VERSION . "\r\n";

    $message  = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body_plain . "\r\n\r\n";

    foreach ($attachments as $att) {
        $mime     = str_ends_with(strtolower($att['path']), '.png') ? 'image/png' : 'image/jpeg';
        $encoded  = chunk_split(base64_encode(file_get_contents($att['path'])));
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: {$mime}; name=\"{$att['name']}\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
        $message .= $encoded . "\r\n";
    }
    $message .= "--{$boundary}--";

    return mail($to, $subject, $message, $headers);
}
