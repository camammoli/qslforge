<?php
session_init();
// Language switch via GET
if (!empty($_GET['lang'])) { set_lang($_GET['lang']); header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit; }
$_yo = usuario_actual();
$_flashes = get_flashes();
?><!DOCTYPE html>
<html lang="<?= detect_lang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($page_title) ? h($page_title) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background:#1a2a3a">
  <div class="container-lg">
    <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/">
      <i class="bi bi-envelope-paper-heart me-2 text-warning"></i><?= APP_NAME ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?= APP_URL ?>/generate.php">
            <i class="bi bi-magic me-1"></i><?= t('nav_generate') ?>
          </a>
        </li>
      </ul>
      <ul class="navbar-nav align-items-center gap-1">
        <!-- Language switcher -->
        <li class="nav-item">
          <a class="nav-link text-warning" href="?lang=<?= t('lang_switch_code') ?>">
            <?= t('lang_switch') ?>
          </a>
        </li>
        <?php if (!empty($_yo)): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= h($_yo['callsign']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= h($_yo['email']) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/account/templates.php"><i class="bi bi-collection me-1"></i><?= t('nav_templates') ?></a></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/account/history.php"><i class="bi bi-clock-history me-1"></i><?= t('nav_history') ?></a></li>
            <?php if (is_admin()): ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= APP_URL ?>/admin/"><i class="bi bi-gear me-1"></i><?= t('nav_admin') ?></a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/account/logout.php"><i class="bi bi-box-arrow-right me-1"></i><?= t('nav_logout') ?></a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= APP_URL ?>/account/login.php"><?= t('nav_login') ?></a>
        </li>
        <li class="nav-item">
          <a class="btn btn-outline-warning btn-sm" href="<?= APP_URL ?>/account/register.php"><?= t('nav_register') ?></a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container-lg py-4">
<?php foreach ($_flashes as $f): ?>
  <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show" role="alert">
    <?= $f['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
