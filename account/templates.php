<?php
require_once __DIR__ . '/../config.php';
session_init();
require_login();
$page_title = t('templates_title');

$pdo = get_pdo();
$uid = (int)$_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $tid = (int)$_POST['tid'];
        $pdo->prepare("DELETE FROM templates WHERE id=? AND usuario_id=?")->execute([$tid, $uid]);
        flash('success', 'Template deleted.');
    } elseif ($action === 'set_default') {
        $tid = (int)$_POST['tid'];
        $pdo->prepare("UPDATE templates SET is_default=0 WHERE usuario_id=?")->execute([$uid]);
        $pdo->prepare("UPDATE templates SET is_default=1 WHERE id=? AND usuario_id=?")->execute([$tid, $uid]);
        flash('success', 'Default template updated.');
    } elseif ($action === 'load') {
        $tid = (int)$_POST['tid'];
        $st  = $pdo->prepare("SELECT config FROM templates WHERE id=? AND usuario_id=?");
        $st->execute([$tid, $uid]);
        $row = $st->fetch();
        if ($row) {
            $_SESSION['gen_template'] = json_decode($row['config'], true);
            flash('success', 'Template loaded — continue from Step 2.');
            header('Location: ' . APP_URL . '/generate.php?step=2');
            exit;
        }
    }
    header('Location: ' . APP_URL . '/account/templates.php');
    exit;
}

$st = $pdo->prepare("SELECT * FROM templates WHERE usuario_id=? ORDER BY is_default DESC, updated_at DESC");
$st->execute([$uid]);
$templates = $st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= APP_URL ?>/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h5 class="fw-bold mb-0"><i class="bi bi-collection me-2 text-warning"></i><?= t('templates_title') ?></h5>
</div>

<?php if (!$templates): ?>
<div class="alert alert-secondary"><?= t('templates_empty') ?></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($templates as $tpl): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100 <?= $tpl['is_default'] ? 'border-warning' : '' ?>">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold small"><?= h($tpl['nombre']) ?></span>
        <?php if ($tpl['is_default']): ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Default</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-2">
        <?php
        $cfg = json_decode($tpl['config'], true);
        $visible = array_filter($cfg['fields'] ?? [], fn($f) => $f['visible']);
        ?>
        <div class="small text-muted"><?= count($visible) ?> visible fields</div>
        <div class="mt-1" style="font-size:.7rem">
          <?php foreach ($visible as $fname => $fc): ?>
          <span class="badge bg-secondary me-1"><?= h(t('f_' . $fname)) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer p-2 d-flex gap-1 flex-wrap">
        <form method="post" class="d-inline">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="load">
          <input type="hidden" name="tid" value="<?= $tpl['id'] ?>">
          <button class="btn btn-sm btn-warning py-0"><i class="bi bi-play-fill me-1"></i>Use</button>
        </form>
        <?php if (!$tpl['is_default']): ?>
        <form method="post" class="d-inline">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="set_default">
          <input type="hidden" name="tid" value="<?= $tpl['id'] ?>">
          <button class="btn btn-sm btn-outline-warning py-0"><i class="bi bi-star me-1"></i>Default</button>
        </form>
        <?php endif; ?>
        <form method="post" class="d-inline"
              onsubmit="return confirm('Delete this template?')">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="tid" value="<?= $tpl['id'] ?>">
          <button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mt-4">
  <a href="<?= APP_URL ?>/generate.php" class="btn btn-outline-warning">
    <i class="bi bi-magic me-1"></i>Generate new batch
  </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
