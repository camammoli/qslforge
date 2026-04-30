<?php
require_once __DIR__ . '/../config.php';
session_init();
if (is_logged()) { header('Location: ' . APP_URL . '/'); exit; }
$page_title = t('login_title');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['pass'] ?? '';
    try {
        $st = get_pdo()->prepare("SELECT * FROM usuarios WHERE email=? AND activo=1");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            get_pdo()->prepare("UPDATE usuarios SET ultimo_login=NOW() WHERE id=?")->execute([$u['id']]);
            set_lang($u['lang']);
            $next = $_GET['next'] ?? ($_POST['next'] ?? '');
            // Validate next
            if ($next && str_starts_with($next, APP_URL . '/')) {
                header('Location: ' . $next);
            } else {
                header('Location: ' . APP_URL . '/');
            }
            exit;
        }
        $error = t('err_login');
    } catch (PDOException $e) { $error = t('err_login'); }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
<div class="col-sm-10 col-md-7 col-lg-5">
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-person me-2"></i><?= t('login_title') ?></div>
  <div class="card-body">
    <?php if ($error): ?><div class="alert alert-danger small"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="next" value="<?= h($_GET['next'] ?? '') ?>">
      <div class="mb-3">
        <label class="form-label"><?= t('login_email') ?></label>
        <input type="email" name="email" class="form-control" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label"><?= t('login_pass') ?></label>
        <input type="password" name="pass" class="form-control" required>
      </div>
      <button class="btn btn-warning w-100 fw-semibold"><?= t('login_btn') ?></button>
    </form>
    <div class="text-center mt-3 small">
      <?= t('login_no_account') ?>
      <a href="<?= APP_URL ?>/account/register.php"><?= t('nav_register') ?></a>
    </div>
  </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
