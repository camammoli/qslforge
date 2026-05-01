<?php
require_once __DIR__ . '/adif.php';

function draw_grid_on_card($img, int $W, int $H, array $template): void {
    $mode    = $template['grid_mode']  ?? 'free';
    $draw    = $template['grid_draw']  ?? false;
    $hex     = ltrim($template['grid_color'] ?? '#ffffff', '#');
    $opacity = max(10, min(90, (int)($template['grid_alpha'] ?? 50)));

    if (!$draw || $mode === 'free') return;

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    imagealphablending($img, true);
    // GD alpha: 0=opaque, 127=transparent
    $aMain = (int)round((1 - $opacity / 100) * 127);
    $aFill = min(127, $aMain + 50);
    $cMain = imagecolorallocatealpha($img, $r, $g, $b, $aMain);
    $cFill = imagecolorallocatealpha($img, $r, $g, $b, $aFill);

    if ($mode === 'grid_h') {
        // Separator line between identity area and data table
        imagesetthickness($img, 2);
        imageline($img, 0, (int)(.58*$H), $W, (int)(.58*$H), $cMain);

        $tL  = (int)(.04*$W);  $tR  = (int)(.97*$W);
        $rY  = [(int)(.63*$H), (int)(.74*$H), (int)(.85*$H), (int)(.96*$H)];

        // Subtle fill inside the table area
        imagefilledrectangle($img, $tL, $rY[0], $tR, $rY[3], $cFill);
        // Table border
        imagesetthickness($img, 2);
        imagerectangle($img, $tL, $rY[0], $tR, $rY[3], $cMain);
        // Column dividers
        imagesetthickness($img, 1);
        foreach ([.26, .44, .60, .76] as $f) {
            $x = (int)($f * $W);
            imageline($img, $x, $rY[0], $x, $rY[3], $cMain);
        }
        // Row dividers
        imageline($img, $tL, $rY[1], $tR, $rY[1], $cMain);
        imageline($img, $tL, $rY[2], $tR, $rY[2], $cMain);

    } elseif ($mode === 'grid_v') {
        // Separator after callsign
        imagesetthickness($img, 2);
        imageline($img, 0, (int)(.21*$H), $W, (int)(.21*$H), $cMain);
        // Center vertical divider
        imageline($img, (int)(.50*$W), (int)(.21*$H), (int)(.50*$W), (int)(.98*$H), $cMain);
        // Horizontal row lines
        imagesetthickness($img, 1);
        foreach ([.28, .40, .52, .64, .76, .87, .95] as $f) {
            imageline($img, 0, (int)($f*$H), $W, (int)($f*$H), $cMain);
        }
    }
}

function generate_card(string $bg_path, array $qso, array $template, string $out_path): bool {
    // Load background
    $info = @getimagesize($bg_path);
    if (!$info) return false;

    $src_type = $info[2];
    $raw = match($src_type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($bg_path),
        IMAGETYPE_PNG  => @imagecreatefrompng($bg_path),
        default        => false,
    };
    if (!$raw) return false;

    $out_ext = strtolower(pathinfo($out_path, PATHINFO_EXTENSION));

    // If source is PNG with alpha and output is JPG, composite over white
    if ($src_type === IMAGETYPE_PNG && $out_ext !== 'png') {
        $w   = imagesx($raw);
        $h   = imagesy($raw);
        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        imagealphablending($raw, true);
        imagecopy($img, $raw, 0, 0, 0, 0, $w, $h);
        imagedestroy($raw);
    } else {
        $img = $raw;
        if ($src_type === IMAGETYPE_PNG) {
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }
    }

    if (!$img) return false;

    $W = imagesx($img);
    $H = imagesy($img);
    draw_grid_on_card($img, $W, $H, $template);

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
    $info = @getimagesize($bg_path);
    $ext  = ($info && $info[2] === IMAGETYPE_PNG) ? 'png' : 'jpg';
    $tmp  = sys_get_temp_dir() . '/qslprev_' . session_id() . '.' . $ext;
    if (generate_card($bg_path, $qso, $template, $tmp)) {
        $data = base64_encode(file_get_contents($tmp));
        @unlink($tmp);
        return $data;
    }
    @unlink($tmp);
    return null;
}
