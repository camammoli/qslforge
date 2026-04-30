<?php
function detect_lang(): string {
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['en','es'])) return $_SESSION['lang'];
    if (!empty($_COOKIE['lang'])  && in_array($_COOKIE['lang'],  ['en','es'])) return $_COOKIE['lang'];
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
    return str_starts_with(strtolower($accept), 'es') ? 'es' : 'en';
}

function set_lang(string $lang): void {
    if (!in_array($lang, ['en','es'])) return;
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + 86400 * 365, '/');
}

function t(string $key, array $vars = []): string {
    static $strings = null;
    if ($strings === null) {
        $lang    = detect_lang();
        $file    = BASE_DIR . '/includes/i18n/' . $lang . '.php';
        $strings = file_exists($file) ? require $file : [];
        if ($lang !== 'en') {
            $en = require BASE_DIR . '/includes/i18n/en.php';
            $strings = array_merge($en, $strings);
        }
    }
    $str = $strings[$key] ?? $key;
    foreach ($vars as $k => $v) $str = str_replace('{' . $k . '}', $v, $str);
    return $str;
}
