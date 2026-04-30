
</div><!-- container -->

<footer class="border-top mt-5 py-3 text-muted small">
  <div class="container-lg d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-envelope-paper-heart me-1 text-warning"></i><?= t('footer_text') ?></span>
    <span>
      <a href="<?= APP_URL ?>/" class="text-muted text-decoration-none me-3"><?= t('nav_home') ?></a>
      <a href="<?= APP_URL ?>/generate.php" class="text-muted text-decoration-none me-3"><?= t('nav_generate') ?></a>
      <a href="?lang=<?= t('lang_switch_code') ?>" class="text-muted text-decoration-none"><?= t('lang_switch') ?></a>
    </span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
