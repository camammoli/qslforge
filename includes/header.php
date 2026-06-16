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
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <?php if (!empty($meta_description)): ?><meta name="description" content="<?= h($meta_description) ?>"><?php endif; ?>
  <?php if (empty($noindex)): ?>
  <link rel="alternate" hreflang="es" href="<?= APP_URL ?>/?lang=es">
  <link rel="alternate" hreflang="en" href="<?= APP_URL ?>/?lang=en">
  <link rel="alternate" hreflang="x-default" href="<?= APP_URL ?>/">
  <?php endif; ?>
  <?php if (!empty($json_ld)): ?><script type="application/ld+json"><?= json_encode($json_ld, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
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
        <li class="nav-item">
          <button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#modalBugReport" title="<?= t('bug_report_title') ?>">
            <i class="bi bi-bug"></i>
          </button>
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

<!-- Modal bug report -->
<div class="modal fade" id="modalBugReport" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#1a2a3a;color:#fff">
        <h5 class="modal-title"><i class="bi bi-bug me-2 text-warning"></i><?= t('bug_report_title') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3"><?= t('bug_report_intro') ?></p>
        <div id="bug-result" class="d-none mb-3"></div>
        <div class="mb-3">
          <label class="form-label small fw-semibold"><?= t('bug_report_call') ?></label>
          <input type="text" id="bug-call" class="form-control form-control-sm" maxlength="15" placeholder="LU2MCA">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold"><?= t('bug_report_email') ?> *</label>
          <input type="email" id="bug-email" class="form-control form-control-sm" placeholder="tu@email.com"
                 value="<?= h($_yo['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold"><?= t('bug_report_url') ?></label>
          <input type="text" id="bug-url" class="form-control form-control-sm" value="">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold"><?= t('bug_report_desc') ?> *</label>
          <textarea id="bug-desc" class="form-control form-control-sm" rows="4" maxlength="2000"
                    placeholder="<?= t('bug_report_desc') ?>…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('step_back') ?></button>
        <button type="button" class="btn btn-warning" id="btn-bug-send" onclick="sendBugReport()">
          <i class="bi bi-send me-1"></i><?= t('bug_report_send') ?>
        </button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('bug-url').value = window.location.href;
function sendBugReport() {
  const email = document.getElementById('bug-email').value.trim();
  const desc  = document.getElementById('bug-desc').value.trim();
  if (!email || !desc) return;
  const btn = document.getElementById('btn-bug-send');
  btn.disabled = true;
  fetch('<?= APP_URL ?>/api/bug_report.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      call: document.getElementById('bug-call').value.trim(),
      email,
      url:  document.getElementById('bug-url').value,
      desc,
    })
  })
  .then(r => r.json())
  .then(d => {
    btn.disabled = false;
    const el = document.getElementById('bug-result');
    el.classList.remove('d-none');
    el.innerHTML = d.ok
      ? '<div class="alert alert-success py-2 small"><?= t('bug_report_ok') ?></div>'
      : '<div class="alert alert-danger py-2 small"><?= t('bug_report_err') ?></div>';
    if (d.ok) { document.getElementById('bug-desc').value = ''; }
  })
  .catch(() => { btn.disabled = false; });
}
</script>

<div class="container-lg py-4">
<?php foreach ($_flashes as $f): ?>
  <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show" role="alert">
    <?= $f['msg'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
