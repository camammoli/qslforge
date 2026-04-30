<?php
require_once __DIR__ . '/config.php';
$page_title = t('hero_title');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<div class="text-center py-5 mb-4" style="background:linear-gradient(135deg,#1a2a3a 0%,#0d1b2a 100%);border-radius:1rem;color:#fff">
  <i class="bi bi-envelope-paper-heart display-3 text-warning mb-3 d-block"></i>
  <h1 class="fw-bold display-5"><?= t('hero_title') ?></h1>
  <p class="lead text-white-50 mb-4 mx-auto" style="max-width:560px"><?= t('hero_sub') ?></p>
  <a href="<?= APP_URL ?>/generate.php" class="btn btn-warning btn-lg px-5 fw-semibold">
    <i class="bi bi-magic me-2"></i><?= t('hero_cta') ?>
  </a>
</div>

<!-- Beta notice -->
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4 small">
  <i class="bi bi-cone-striped fs-5 flex-shrink-0"></i>
  <span>
    <strong>Beta pública.</strong>
    <?= t('lang_switch_code') === 'es'
      ? 'This is an early version — expect improvements in fonts, layout options, email delivery and more. Your feedback helps.'
      : 'Esta es una versión temprana — habrá mejoras en fuentes, opciones de diseño, envío de emails y más. Tu feedback ayuda.' ?>
    &nbsp;<a href="#" class="alert-link" data-bs-toggle="modal" data-bs-target="#modalBugReport"><?= t('footer_bug') ?></a>
  </span>
</div>

<!-- Features -->
<div class="row g-4 mb-5">
  <?php
  $feats = [
    ['bi-file-earmark-text', 'warning', 'feat_1_title', 'feat_1_desc'],
    ['bi-palette',           'info',    'feat_2_title', 'feat_2_desc'],
    ['bi-archive',           'success', 'feat_3_title', 'feat_3_desc'],
    ['bi-bookmark-star',     'primary', 'feat_4_title', 'feat_4_desc'],
  ];
  foreach ($feats as [$icon, $color, $title, $desc]):
  ?>
  <div class="col-md-6 col-lg-3">
    <div class="card h-100 border-0 shadow-sm text-center p-3">
      <div class="mb-3"><i class="bi <?= $icon ?> display-5 text-<?= $color ?>"></i></div>
      <h6 class="fw-bold"><?= t($title) ?></h6>
      <p class="small text-muted mb-0"><?= t($desc) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- How it works -->
<div class="card border-0 bg-body-secondary mb-5">
  <div class="card-body py-4">
    <h5 class="fw-bold text-center mb-4">How it works</h5>
    <div class="row g-3 text-center">
      <?php
      $steps = [
        ['bi-upload',          '1', t('step1_title'), 'Upload your ADIF log and your QSL card artwork.'],
        ['bi-sliders',         '2', t('step2_title'), 'Place callsign, date, band and other fields on the card.'],
        ['bi-download',        '3', t('step3_title'), 'Download the complete ZIP or send by email to each OM.'],
      ];
      foreach ($steps as [$icon, $num, $title, $desc]):
      ?>
      <div class="col-md-4">
        <div class="bg-white rounded p-3 h-100 shadow-sm">
          <div class="fw-bold text-muted small mb-1">Step <?= $num ?></div>
          <i class="bi <?= $icon ?> fs-2 text-warning d-block mb-2"></i>
          <div class="fw-semibold"><?= h($title) ?></div>
          <div class="small text-muted mt-1"><?= h($desc) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="text-center">
  <a href="<?= APP_URL ?>/generate.php" class="btn btn-warning btn-lg px-5 fw-semibold">
    <i class="bi bi-magic me-2"></i><?= t('hero_cta') ?>
  </a>
  <div class="mt-3 small text-muted">
    <?= t('already_account') ?>
    <a href="<?= APP_URL ?>/account/login.php"><?= t('nav_login') ?></a>
    &nbsp;·&nbsp;
    <?= t('login_no_account') ?>
    <a href="<?= APP_URL ?>/account/register.php"><?= t('nav_register') ?></a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
