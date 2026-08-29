<?php
require_once __DIR__ . '/../config.php';
session_init();
if (is_logged()) { header('Location: ' . APP_URL . '/'); exit; }
$page_title = t('register_title');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Anti-spam: honeypot y trampa de tiempo. Un bot que llena el form no
    // recibe ninguna pista de qué lo frenó — le mostramos el mismo mensaje
    // de éxito que a un registro real, pero no se crea ninguna cuenta.
    if (!empty($_POST['callsign2'])) {
        flash('success', t('register_ok'));
        header('Location: ' . APP_URL . '/'); exit;
    }
    if (time() - ($_SESSION['reg_ts'] ?? 0) < 2) {
        flash('success', t('register_ok'));
        header('Location: ' . APP_URL . '/'); exit;
    }

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
        // Rate limit: 5 registros por IP por hora, sin DB — este sí es un
        // error real y visible, un humano de verdad casi nunca lo choca.
        $ipClave = substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'x'), 0, 16);
        $archivoLimite = sys_get_temp_dir() . '/qslf_registro_' . $ipClave . '.json';
        $marcas = file_exists($archivoLimite) ? (@json_decode(file_get_contents($archivoLimite), true) ?? []) : [];
        $marcas = array_values(array_filter($marcas, fn($t) => $t > time() - 3600));
        if (count($marcas) >= 5) {
            $error = t('err_rate_limit');
        } else {
        try {
            $pdo = get_pdo();
            $ex  = $pdo->prepare("SELECT id FROM usuarios WHERE email=?"); $ex->execute([$email]);
            if ($ex->fetch()) {
                $error = t('err_email_taken');
            } else {
                $marcas[] = time();
                @file_put_contents($archivoLimite, json_encode($marcas));
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
}

// Trampa de tiempo: momento en que se (re)muestra el form — se reinicia acá,
// justo antes de renderizar, tanto en la carga inicial como si el POST de
// arriba terminó en un error real que vuelve a mostrar el formulario.
$_SESSION['reg_ts'] = time();

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
      <div class="cf-hp" aria-hidden="true">
        <label for="callsign2">Leave blank</label>
        <input type="text" id="callsign2" name="callsign2" tabindex="-1" autocomplete="off">
      </div>
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
