<?php
require_once __DIR__ . '/../config.php';
session_init();
$_SESSION = [];
session_destroy();
header('Location: ' . APP_URL . '/');
