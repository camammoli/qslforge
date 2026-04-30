<?php
function adif_parse(string $content): array {
    $qsos = [];
    // Skip header (everything before <EOH>)
    if (($eoh = stripos($content, '<EOH>')) !== false) {
        $content = substr($content, $eoh + 5);
    }
    // Split by <EOR>
    $records = preg_split('/<EOR\s*>/i', $content);
    foreach ($records as $record) {
        if (!trim($record)) continue;
        $qso = [];
        $pos = 0;
        $len = strlen($record);
        while ($pos < $len) {
            // Find next tag <FIELDNAME:SIZE> or <FIELDNAME:SIZE:TYPE>
            if (!preg_match('/<([A-Za-z_][A-Za-z_0-9]*):(\d+)(?::[^>]*)?>/i', $record, $m, PREG_OFFSET_CAPTURE, $pos)) break;
            $field    = strtoupper($m[1][0]);
            $size     = (int)$m[2][0];
            $tag_end  = $m[0][1] + strlen($m[0][0]);
            $value    = substr($record, $tag_end, $size);
            $qso[$field] = trim($value);
            $pos = $tag_end + $size;
        }
        if (!empty($qso['CALL'])) $qsos[] = $qso;
    }
    return $qsos;
}

function adif_field_value(array $qso, string $field): string {
    $raw = $qso[$field] ?? '';
    return match($field) {
        'QSO_DATE' => fmt_date_adif($raw),
        'TIME_ON'  => fmt_time_adif($raw),
        default    => $raw,
    };
}

function adif_summary(array $qsos): array {
    $calls = array_unique(array_column($qsos, 'CALL'));
    $bands  = array_unique(array_filter(array_column($qsos, 'BAND')));
    $modes  = array_unique(array_filter(array_column($qsos, 'MODE')));
    return [
        'total'  => count($qsos),
        'calls'  => count($calls),
        'bands'  => implode(', ', $bands),
        'modes'  => implode(', ', $modes),
    ];
}
