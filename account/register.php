<?php
require_once __DIR__ . '/../config.php';
session_init();
if (is_logged()) { header('Location: ' . APP_URL . '/'); exit; }
$page_title = t('register_title');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $callsign = strtoupper(trim($_POST['callsign'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $pass     = $_POST['pass'] ?? '';
    $pass2    = $_POST['pass2'] ?? '';
    $lang     = detect_lang();

    if (!$callsign)                       $error = t('err_call_required');
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = t('err_email');
    elseif (strlen($pass) < 6)            $error = t('err_pass_short');
    elseif ($pass !== $pass2)             $error = t('err_pass_match');
    else {
        try {
            $pdo = get_pdo();
            $ex  = $pdo->prepare("SELECT id FROM usuarios WHERE email=?"); $ex->execute([$email]);
            if ($ex->fetch()) {
                $error = t('err_email_taken');
            } else {
                $pdo->prepare("INSERT INTO usuarios (callsign,email,password_hash,lang) VALUES (?,?,?,?)")
                    ->execute([$callsign, $email, password_hash($pass, PASSWORD_BCRYPT), $lang]);
                $uid = (int)$pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['uid'] = $uid;
                flash('success', t('register_ok'));
                header('Location: ' . APP_URL . '/');
                exit;
            }
        } catch (PDOException $e) { $error = 'Database error — please try again.'; }
    }
}

$noindex = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
<div class="col-sm-10 col-md-7 col-lg-5">
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-person-plus me-2"></i><?= t('register_title') ?></div>
  <div class="card-body">
    <?php if ($error): ?><div class="alert alert-danger small"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_input() ?>
      <div class="mb-3">
        <label class="form-label"><?= t('register_call') ?></label>
        <input type="text" name="callsign" class="form-control text-uppercase" required autofocus
               maxlength="20" value="<?= h($_POST['callsign'] ?? '') ?>" placeholder="LU2MCA">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= t('register_email') ?></label>
        <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= t('register_pass') ?></label>
        <input type="password" name="pass" class="form-control" required minlength="6">
      </div>
      <div class="mb-4">
        <label class="form-label"><?= t('register_pass2') ?></label>
        <input type="password" name="pass2" class="form-control" required minlength="6">
      </div>
      <button class="btn btn-warning w-100 fw-semibold"><?= t('register_btn') ?></button>
    </form>
    <div class="text-center mt-3 small">
      <?= t('already_account') ?>
      <a href="<?= APP_URL ?>/account/login.php"><?= t('nav_login') ?></a>
    </div>
  </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
