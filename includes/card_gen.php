<?php
require_once __DIR__ . '/adif.php';

function generate_card(string $bg_path, array $qso, array $template, string $out_path): bool {
    // Load background
    $info = @getimagesize($bg_path);
    if (!$info) return false;
    $img = match($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($bg_path),
        IMAGETYPE_PNG  => @imagecreatefrompng($bg_path),
        default        => false,
    };
    if (!$img) return false;

    $fields = $template['fields'] ?? [];

    foreach ($fields as $field => $cfg) {
        if (empty($cfg['visible'])) continue;

        $value = $field === 'CUSTOM'
            ? ($cfg['custom_text'] ?? '')
            : adif_field_value($qso, $field);

        if ($value === '' && $field !== 'CUSTOM') continue;

        $text   = ($cfg['prefix'] ?? '') . $value;
        $x      = (int)($cfg['x']    ?? 100);
        $y      = (int)($cfg['y']    ?? 100);
        $size   = (int)($cfg['size'] ?? 20);
        $font   = font_path($cfg['font'] ?? 'roboto');
        $align  = $cfg['align'] ?? 'left';
        $hex    = ltrim($cfg['color'] ?? '#ffffff', '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $color = imagecolorallocate($img, $r, $g, $b);

        // TTF only if function exists AND font file is present
        $has_ttf = function_exists('imagettftext') && file_exists($font);

        if ($has_ttf) {
            if ($align !== 'left' && function_exists('imagettfbbox')) {
                $box = imagettfbbox($size, 0, $font, $text);
                $tw  = abs($box[4] - $box[0]);
                if ($align === 'center') $x -= (int)($tw / 2);
                elseif ($align === 'right') $x -= $tw;
            }
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
        } else {
            // Fallback: built-in GD fonts (no TTF files needed)
            $fn = min(5, max(1, (int)round($size / 9)));
            $fw = imagefontwidth($fn);
            $fh = imagefontheight($fn);
            if ($align === 'center') $x -= (int)(strlen($text) * $fw / 2);
            elseif ($align === 'right') $x -= strlen($text) * $fw;
            imagestring($img, $fn, $x, $y - $fh, $text, $color);
        }
    }

    // Save
    $ext = strtolower(pathinfo($out_path, PATHINFO_EXTENSION));
    $ok  = $ext === 'png'
        ? imagepng($img, $out_path)
        : imagejpeg($img, $out_path, 92);
    imagedestroy($img);
    return $ok;
}

function generate_batch(string $session_id, string $bg_path, array $qsos, array $template): array {
    $tmp_dir = OUTPUT_DIR . $session_id . '/';
    if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0755, true);

    $ext     = 'jpg';
    $info    = @getimagesize($bg_path);
    if ($info && $info[2] === IMAGETYPE_PNG) $ext = 'png';

    $files   = [];
    $errors  = [];
    $counter = [];

    foreach ($qsos as $i => $qso) {
        $call = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($qso['CALL'] ?? 'UNKNOWN'));
        $date = preg_replace('/[^0-9]/', '', $qso['QSO_DATE'] ?? '');
        // Handle duplicate callsigns in the same log
        $counter[$call] = ($counter[$call] ?? 0) + 1;
        $suffix = $counter[$call] > 1 ? '_' . $counter[$call] : '';
        $fname  = $call . ($date ? '_' . $date : '') . $suffix . '.' . $ext;
        $out    = $tmp_dir . $fname;

        if (generate_card($bg_path, $qso, $template, $out)) {
            $files[] = ['path' => $out, 'name' => $fname, 'call' => $call, 'qso' => $qso];
        } else {
            $errors[] = $call;
        }
    }

    return ['files' => $files, 'errors' => $errors, 'dir' => $tmp_dir];
}

function build_zip(array $files, string $zip_path): bool {
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    foreach ($files as $f) {
        $zip->addFile($f['path'], $f['name']);
    }
    $zip->close();
    return file_exists($zip_path);
}

function generate_preview(string $bg_path, array $qso, array $template): ?string {
    $tmp = OUTPUT_DIR . 'preview_' . session_id() . '.jpg';
    if (generate_card($bg_path, $qso, $template, $tmp)) {
        return $tmp;
    }
    return null;
}
