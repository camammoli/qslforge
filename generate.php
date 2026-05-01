<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/adif.php';
require_once __DIR__ . '/includes/card_gen.php';
session_init();

$page_title = t('nav_generate');

// ── Step logic ────────────────────────────────────────────────────────────────
$step  = (int)($_GET['step'] ?? $_SESSION['gen_step'] ?? 1);
$error = '';

// Limpiar sesión en cualquier GET sin param step (reload desde cualquier punto → vuelve a step 1)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['step'])) {
    foreach (['gen_qsos','gen_bg','gen_step','gen_template','gen_summary','gen_adif_name',
              'gen_uuid','gen_zip','gen_files','gen_zip_name'] as $k) {
        unset($_SESSION[$k]);
    }
}

// ── Step 1 POST: upload ADIF + background ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    verify_csrf();

    $adif_file = $_FILES['adif'] ?? null;
    $bg_file   = $_FILES['background'] ?? null;

    $adif_ext = strtolower(pathinfo($adif_file['name'] ?? '', PATHINFO_EXTENSION));
    if (!$adif_file || $adif_file['error'] !== UPLOAD_ERR_OK) {
        $error = t('err_adif_required');
    } elseif (!in_array($adif_ext, ['adi', 'adif'])) {
        $error = t('err_adif_required');
    } elseif ($adif_file['size'] > MAX_ADIF_MB * 1024 * 1024) {
        $error = t('err_adif_toolarge');
    } elseif (!$bg_file || $bg_file['error'] !== UPLOAD_ERR_OK) {
        $error = t('err_bg_required');
    } elseif ($bg_file['size'] > MAX_IMG_MB * 1024 * 1024) {
        $error = t('err_bg_toolarge');
    } else {
        // Validate ADIF content has recognizable ADIF markers
        $adif_content = file_get_contents($adif_file['tmp_name']);
        if (!adif_looks_valid($adif_content)) {
            security_log('invalid_adif_content', ['name' => $adif_file['name'], 'size' => $adif_file['size']]);
            $error = t('err_adif_invalid');
        } else {
            $mime = mime_content_type($bg_file['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png'])) {
                security_log('invalid_image_mime', ['name' => $bg_file['name'], 'mime' => $mime]);
                $error = t('err_bg_invalid');
            } else {
            // Re-process image through GD: strips metadata and ensures it's a valid image
            $img = $mime === 'image/png'
                ? @imagecreatefrompng($bg_file['tmp_name'])
                : @imagecreatefromjpeg($bg_file['tmp_name']);
            if (!$img) {
                security_log('invalid_image_gd', ['name' => $bg_file['name'], 'mime' => $mime]);
                $error = t('err_bg_invalid');
            } else {
            // Parse ADIF
            $qsos = adif_parse($adif_content);
            if (empty($qsos)) {
                imagedestroy($img);
                $error = t('err_adif_empty');
            } else {
                // Save files to uploads/session/
                $sid = session_id();
                $upload_path = UPLOAD_DIR . $sid . '/';
                if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);

                $bg_ext  = $mime === 'image/png' ? 'png' : 'jpg';
                $bg_dest = $upload_path . 'background.' . $bg_ext;
                if ($mime === 'image/png') {
                    imagepng($img, $bg_dest, 6);
                } else {
                    imagejpeg($img, $bg_dest, 92);
                }
                imagedestroy($img);

                $_SESSION['gen_qsos']    = $qsos;
                $_SESSION['gen_bg']      = $bg_dest;
                $_SESSION['gen_step']    = 2;
                $_SESSION['gen_summary'] = adif_summary($qsos);
                $_SESSION['gen_adif_name'] = basename($adif_file['name']);

                // Load user's default template if logged in
                if (is_logged()) {
                    try {
                        $st = get_pdo()->prepare("SELECT config FROM templates WHERE usuario_id=? AND is_default=1 LIMIT 1");
                        $st->execute([$_SESSION['uid']]);
                        $tpl = $st->fetchColumn();
                        $_SESSION['gen_template'] = $tpl ? json_decode($tpl, true) : default_template();
                    } catch (PDOException $e) {
                        $_SESSION['gen_template'] = default_template();
                    }
                } else {
                    $_SESSION['gen_template'] = default_template();
                }

                header('Location: ' . APP_URL . '/generate.php?step=2');
                exit;
            }
            } // end if (!$img)
            } // end if (!in_array mime)
        } // end if (!adif_looks_valid)
    }
    $step = 1;
}

// ── Step 2 POST: save design config ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'design') {
    verify_csrf();
    if (empty($_SESSION['gen_qsos'])) { flash('error', t('err_session')); header('Location: ' . APP_URL . '/generate.php'); exit; }

    $tpl = $_SESSION['gen_template'] ?? default_template();
    $fields = array_keys($tpl['fields']);
    foreach ($fields as $f) {
        $tpl['fields'][$f]['visible'] = !empty($_POST['vis_' . $f]);
        $tpl['fields'][$f]['x']       = (int)($_POST['x_' . $f]      ?? $tpl['fields'][$f]['x']);
        $tpl['fields'][$f]['y']       = (int)($_POST['y_' . $f]      ?? $tpl['fields'][$f]['y']);
        $tpl['fields'][$f]['size']    = max(8, min(200, (int)($_POST['size_' . $f] ?? $tpl['fields'][$f]['size'])));
        $tpl['fields'][$f]['color']   = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color_' . $f] ?? '') ? $_POST['color_' . $f] : $tpl['fields'][$f]['color'];
        $tpl['fields'][$f]['font']    = array_key_exists($_POST['font_' . $f]  ?? '', allowed_fonts()) ? $_POST['font_' . $f]  : ($tpl['fields'][$f]['font']  ?? 'roboto');
        $tpl['fields'][$f]['align']   = in_array($_POST['align_' . $f] ?? '', ['left','center','right']) ? $_POST['align_' . $f] : ($tpl['fields'][$f]['align'] ?? 'left');
        $tpl['fields'][$f]['prefix']  = substr($_POST['prefix_' . $f] ?? ($tpl['fields'][$f]['prefix'] ?? ''), 0, 50);
        if ($f === 'CUSTOM') $tpl['fields'][$f]['custom_text'] = substr($_POST['custom_text'] ?? ($tpl['fields'][$f]['custom_text'] ?? ''), 0, 200);
    }
    $_SESSION['gen_template'] = $tpl;

    // Save template if requested
    if (!empty($_POST['save_template']) && is_logged()) {
        $tname = trim(substr($_POST['template_name'] ?? 'My template', 0, 100));
        try {
            $pdo = get_pdo();
            $is_def = empty($pdo->query("SELECT COUNT(*) FROM templates WHERE usuario_id=" . (int)$_SESSION['uid'])->fetchColumn()) ? 1 : 0;
            $pdo->prepare("INSERT INTO templates (usuario_id, nombre, config, is_default) VALUES (?,?,?,?)")
                ->execute([$_SESSION['uid'], $tname, json_encode($tpl), $is_def]);
            flash('success', 'Template saved!');
        } catch (PDOException $e) {}
    }

    $_SESSION['gen_step'] = 3;
    header('Location: ' . APP_URL . '/generate.php?step=3');
    exit;
}

// ── Guards ─────────────────────────────────────────────────────────────────────
if ($step === 2 && empty($_SESSION['gen_qsos'])) { $step = 1; }
if ($step === 3 && empty($_SESSION['gen_qsos'])) { $step = 1; }

$qsos     = $_SESSION['gen_qsos']    ?? [];
$summary  = $_SESSION['gen_summary'] ?? [];
$template = $_SESSION['gen_template'] ?? default_template();
$bg_path  = $_SESSION['gen_bg']      ?? '';
$first    = $qsos[0] ?? [];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Progress bar -->
<div class="d-flex align-items-center gap-3 mb-4">
  <?php foreach ([1 => t('step1_title'), 2 => t('step2_title'), 3 => t('step3_title')] as $n => $label): ?>
  <div class="d-flex align-items-center gap-2 <?= $n < $step ? 'text-success' : ($n === $step ? 'fw-bold' : 'text-muted') ?>">
    <span class="badge rounded-pill <?= $n < $step ? 'bg-success' : ($n === $step ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= $n ?></span>
    <span class="small d-none d-md-inline"><?= h($label) ?></span>
  </div>
  <?php if ($n < 3): ?><div class="flex-grow-1 border-top"></div><?php endif; ?>
  <?php endforeach; ?>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<?php /* ═══════════════════════════════ STEP 1 ══════════════════════════════ */ ?>
<?php if ($step === 1): ?>
<div class="row justify-content-center">
<div class="col-md-7 col-lg-6">
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-upload me-2"></i><?= t('step1_title') ?></div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="upload">

      <div class="mb-4">
        <label class="form-label fw-semibold"><?= t('adif_label') ?></label>
        <input type="file" name="adif" class="form-control" accept=".adi,.adif" required>
        <div class="form-text"><?= t('adif_help', ['max' => MAX_ADIF_MB]) ?></div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold"><?= t('bg_label') ?></label>
        <input type="file" name="background" class="form-control" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required id="bg-input">
        <div class="form-text"><?= t('bg_help', ['max' => MAX_IMG_MB]) ?></div>
        <div id="bg-preview" class="mt-2 d-none">
          <img id="bg-preview-img" class="img-fluid rounded border" style="max-height:200px" alt="preview">
        </div>
      </div>

      <button type="submit" class="btn btn-warning w-100 fw-semibold">
        <i class="bi bi-arrow-right-circle me-1"></i><?= t('upload_btn') ?>
      </button>
    </form>
  </div>
</div>
</div>
</div>
<script>
document.getElementById('bg-input').addEventListener('change', function() {
  const f = this.files[0];
  if (!f) return;
  const url = URL.createObjectURL(f);
  document.getElementById('bg-preview-img').src = url;
  document.getElementById('bg-preview').classList.remove('d-none');
});
</script>

<?php /* ═══════════════════════════════ STEP 2 ══════════════════════════════ */ ?>
<?php elseif ($step === 2): ?>
<div class="row g-4">
  <!-- Form col -->
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-sliders me-2"></i><?= t('step2_title') ?></span>
        <span class="badge bg-secondary"><?= $summary['total'] ?> QSOs</span>
      </div>
      <div class="card-body p-0" style="overflow-y:auto;max-height:75vh">
        <form method="post" id="design-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="design">

          <?php $fonts = allowed_fonts(); ?>
          <?php foreach ($template['fields'] as $field => $cfg): ?>
          <div class="border-bottom p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="fw-semibold small mb-0">
                <input type="checkbox" name="vis_<?= $field ?>" class="form-check-input me-1" <?= $cfg['visible'] ? 'checked' : '' ?> onchange="triggerPreview()">
                <?= t('f_' . $field) ?>
              </label>
            </div>
            <div class="row g-1 g-preview-trigger">
              <div class="col-4">
                <label class="form-label small mb-0"><?= t('field_x') ?></label>
                <input type="number" name="x_<?= $field ?>" class="form-control form-control-sm" value="<?= (int)$cfg['x'] ?>" min="0">
              </div>
              <div class="col-4">
                <label class="form-label small mb-0"><?= t('field_y') ?></label>
                <input type="number" name="y_<?= $field ?>" class="form-control form-control-sm" value="<?= (int)$cfg['y'] ?>" min="0">
              </div>
              <div class="col-4">
                <label class="form-label small mb-0"><?= t('field_size') ?></label>
                <input type="number" name="size_<?= $field ?>" class="form-control form-control-sm" value="<?= (int)$cfg['size'] ?>" min="8" max="200">
              </div>
              <div class="col-4">
                <label class="form-label small mb-0"><?= t('field_color') ?></label>
                <input type="color" name="color_<?= $field ?>" class="form-control form-control-color form-control-sm w-100" value="<?= h($cfg['color']) ?>">
              </div>
              <div class="col-8">
                <label class="form-label small mb-0"><?= t('field_font') ?></label>
                <select name="font_<?= $field ?>" class="form-select form-select-sm">
                  <?php foreach ($fonts as $fk => $fn): ?>
                  <option value="<?= $fk ?>" <?= ($cfg['font'] ?? 'roboto') === $fk ? 'selected' : '' ?>><?= h($fn) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-0"><?= t('field_align') ?></label>
                <select name="align_<?= $field ?>" class="form-select form-select-sm">
                  <?php foreach (['left','center','right'] as $a): ?>
                  <option value="<?= $a ?>" <?= ($cfg['align'] ?? 'left') === $a ? 'selected' : '' ?>><?= t('align_' . $a) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small mb-0"><?= t('field_prefix') ?></label>
                <input type="text" name="prefix_<?= $field ?>" class="form-control form-control-sm" value="<?= h($cfg['prefix'] ?? '') ?>" maxlength="30">
              </div>
              <?php if ($field === 'CUSTOM'): ?>
              <div class="col-12">
                <input type="text" name="custom_text" class="form-control form-control-sm" value="<?= h($cfg['custom_text'] ?? 'Confirming our QSO — TNX!') ?>" placeholder="Fixed text" maxlength="200">
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <?php if (is_logged()): ?>
          <div class="p-3 border-bottom bg-body-secondary">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="save_template" id="chk-save">
              <label class="form-check-label small fw-semibold" for="chk-save"><?= t('save_template') ?></label>
            </div>
            <input type="text" name="template_name" class="form-control form-control-sm" placeholder="<?= t('template_name') ?>" maxlength="100">
          </div>
          <?php endif; ?>

          <div class="p-3">
            <button type="submit" class="btn btn-warning w-100 fw-semibold">
              <i class="bi bi-arrow-right-circle me-1"></i><?= t('step_next') ?>
            </button>
            <a href="<?= APP_URL ?>/generate.php?step=1" class="btn btn-outline-secondary w-100 mt-2"><?= t('step_back') ?></a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Preview col -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-eye me-2"></i>Preview — <?= h($first['CALL'] ?? 'N0CALL') ?></span>
        <div class="d-flex align-items-center gap-2">
          <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary active" id="mode-free"   onclick="setLayoutMode('free')"   title="<?= t('lang_switch_code')==='en'?'Free positioning':'Posicionamiento libre' ?>"><i class="bi bi-arrows-move"></i></button>
            <button type="button" class="btn btn-outline-secondary"        id="mode-grid-h" onclick="setLayoutMode('grid_h')" title="<?= t('lang_switch_code')==='en'?'Horizontal grid':'Grilla horizontal' ?>"><i class="bi bi-list-columns-reverse"></i></button>
            <button type="button" class="btn btn-outline-secondary"        id="mode-grid-v" onclick="setLayoutMode('grid_v')" title="<?= t('lang_switch_code')==='en'?'Vertical grid':'Grilla vertical' ?>"><i class="bi bi-layout-three-columns"></i></button>
          </div>
          <button class="btn btn-sm btn-outline-secondary" id="btn-preview" onclick="loadPreview()">
            <i class="bi bi-arrow-clockwise me-1"></i><?= t('preview_btn') ?>
          </button>
        </div>
      </div>
      <div class="card-body text-center p-2" style="background:#111;min-height:300px;border-radius:0 0 .375rem .375rem">
        <div id="preview-loading" class="text-muted py-4 d-none">
          <div class="spinner-border spinner-border-sm me-2"></div><?= t('preview_loading') ?>
        </div>
        <div id="preview-wrap" style="position:relative;display:inline-block;line-height:0;max-width:100%">
          <img id="preview-img" class="img-fluid rounded" style="max-width:100%;display:none" alt="Card preview">
          <div id="drag-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;display:none;pointer-events:none"></div>
        </div>
        <div id="preview-placeholder" class="text-muted py-5 small">
          <i class="bi bi-image fs-1 d-block mb-2 opacity-25"></i>
          <?= t('preview_btn') ?>
        </div>
      </div>
    </div>
    <div class="text-muted small mt-2 text-center">
      <i class="bi bi-info-circle me-1"></i>
      <?= t('preview_info', [
          'call' => h($first['CALL'] ?? '—'),
          'date' => h(fmt_date_adif($first['QSO_DATE'] ?? '')),
          'band' => h($first['BAND'] ?? '—'),
          'mode' => h($first['MODE'] ?? '—'),
      ]) ?>
    </div>

    <div class="text-muted small mt-1 text-center" id="drag-hint" style="display:none">
      <i class="bi bi-arrows-move me-1 text-primary"></i>
      <?= t('lang_switch_code')==='en'
        ? 'Drag the blue dots to reposition fields. In grid mode, drag <b>⠿</b> to move everything at once.'
        : 'Arrastrá los puntos azules para reposicionar campos. En modo grilla, arrastrá <b>⠿</b> para mover todo junto.' ?>
    </div>

    <!-- Warnings validación -->
    <div id="step2-warnings" class="mt-3 d-none"></div>
  </div>
</div>

<style>
/* ── Handles individuales: puntos pequeños con tooltip ── */
.drag-handle {
  position: absolute;
  width: 14px; height: 14px;
  background: rgba(37,99,235,.75);
  border: 1.5px solid rgba(255,255,255,.9);
  border-radius: 3px;
  cursor: grab;
  user-select: none;
  transform: translate(-50%, -50%);
  pointer-events: auto;
  z-index: 10;
  transition: background .12s, transform .1s;
}
.drag-handle::after {
  content: attr(data-label);
  position: absolute;
  bottom: calc(100% + 5px);
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0,0,0,.82);
  color: #fff;
  font-size: 10px;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity .12s;
}
.drag-handle:hover { background: rgba(29,78,216,.95); transform: translate(-50%,-50%) scale(1.5); }
.drag-handle:hover::after { opacity: 1; }
.drag-handle.dragging { cursor: grabbing !important; background: #f59e0b; z-index: 100; transform: translate(-50%,-50%) scale(1.3); }
/* ── Handle de grilla: mueve todo junto ── */
.grid-move-handle {
  position: absolute;
  top: 4px; left: 4px;
  background: rgba(245,158,11,.9);
  color: #000;
  font-size: 10px; font-weight: 700;
  padding: 3px 9px;
  border-radius: 5px;
  cursor: grab;
  user-select: none;
  pointer-events: auto;
  z-index: 20;
  box-shadow: 0 2px 6px rgba(0,0,0,.3);
}
.grid-move-handle:hover { background: #f59e0b; }
.grid-move-handle.dragging { cursor: grabbing !important; }
/* ── Guías de snap ── */
.snap-guide { position: absolute; opacity: 0; pointer-events: none; transition: opacity .08s; z-index: 50; }
.snap-guide-v { top: 0; bottom: 0; width: 1px; background: #f59e0b; }
.snap-guide-h { left: 0; right: 0; height: 1px; background: #f59e0b; }
</style>
<script>
let previewTimer;
let isDragging = false;
let gridMode   = 'free';
let dragState  = null;
const SNAP_PX  = 8;

// Posiciones predefinidas (fracción de ancho/alto de la imagen)
const GRID_PRESETS = {
  grid_h: {
    CALL:[.04,.07],QSO_DATE:[.04,.20],TIME_ON:[.36,.20],BAND:[.57,.20],FREQ:[.74,.20],
    MODE:[.04,.32],RST_SENT:[.36,.32],RST_RCVD:[.57,.32],
    NAME:[.04,.44],QTH:[.44,.44],GRIDSQUARE:[.04,.56],OPERATOR:[.44,.56],
    COMMENT:[.04,.68],CUSTOM:[.04,.80],
  },
  grid_v: {
    CALL:[.04,.07],
    QSO_DATE:[.04,.22],NAME:[.52,.22],
    TIME_ON:[.04,.34],QTH:[.52,.34],
    BAND:[.04,.46],GRIDSQUARE:[.52,.46],
    MODE:[.04,.58],OPERATOR:[.52,.58],
    FREQ:[.04,.70],COMMENT:[.52,.70],
    RST_SENT:[.04,.82],RST_RCVD:[.28,.82],CUSTOM:[.52,.82],
  }
};

function triggerPreview() {
  if (isDragging) return;
  clearTimeout(previewTimer);
  previewTimer = setTimeout(loadPreview, 800);
}

function loadPreview() {
  const form = document.getElementById('design-form');
  const data = new FormData(form);
  data.append('preview', '1');
  document.getElementById('preview-loading').classList.remove('d-none');
  document.getElementById('preview-placeholder').style.display = 'none';
  document.getElementById('preview-img').style.display = 'none';
  document.getElementById('drag-overlay').style.display = 'none';
  fetch('<?= APP_URL ?>/api/preview.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => {
      document.getElementById('preview-loading').classList.add('d-none');
      if (d.ok) {
        const img = document.getElementById('preview-img');
        img.onload = () => { buildDragOverlay(); document.getElementById('drag-hint').style.display = ''; };
        img.src = 'data:' + d.mime + ';base64,' + d.data;
        img.style.display = '';
      } else {
        document.getElementById('preview-placeholder').style.display = '';
        document.getElementById('preview-placeholder').textContent = d.error || 'Error';
      }
    })
    .catch(() => {
      document.getElementById('preview-loading').classList.add('d-none');
      document.getElementById('preview-placeholder').style.display = '';
    });
}

// ── Modos de layout ───────────────────────────────────────────────────────────
function setLayoutMode(mode) {
  gridMode = mode;
  ['free','grid-h','grid-v'].forEach(m => {
    const btn = document.getElementById('mode-' + m);
    if (btn) btn.classList.toggle('active', m === mode.replace('_','-'));
  });
  if (mode !== 'free') applyGridPreset(mode);
  buildDragOverlay();
  triggerPreview();
}

function applyGridPreset(mode) {
  const img = document.getElementById('preview-img');
  if (!img || !img.naturalWidth) return;
  const W = img.naturalWidth, H = img.naturalHeight;
  const layout = GRID_PRESETS[mode] || {};
  document.querySelectorAll('input[name^="vis_"]:checked').forEach(cb => {
    const f = cb.name.replace('vis_', '');
    if (!layout[f]) return;
    const xi = document.querySelector('[name="x_' + f + '"]');
    const yi = document.querySelector('[name="y_' + f + '"]');
    if (xi) xi.value = Math.round(layout[f][0] * W);
    if (yi) yi.value = Math.round(layout[f][1] * H);
  });
}

// ── Overlay de drag ───────────────────────────────────────────────────────────
function buildDragOverlay() {
  const img     = document.getElementById('preview-img');
  const overlay = document.getElementById('drag-overlay');
  if (!img || img.style.display === 'none' || !img.naturalWidth) return;

  overlay.innerHTML = '';
  overlay.style.display = 'block';

  const scaleX = img.naturalWidth  / img.offsetWidth;
  const scaleY = img.naturalHeight / img.offsetHeight;

  // Handle de grilla (mueve todos los campos a la vez)
  if (gridMode !== 'free') {
    const gh = document.createElement('div');
    gh.className = 'grid-move-handle';
    gh.innerHTML = '&#x2838; ' + (gridMode === 'grid_h' ? 'Grilla H' : 'Grilla V');
    gh.addEventListener('mousedown',  e => startGridDrag(e, scaleX, scaleY));
    gh.addEventListener('touchstart', e => startGridDrag(e, scaleX, scaleY), {passive: false});
    overlay.appendChild(gh);
  }

  // Handles individuales por campo
  document.querySelectorAll('input[name^="vis_"]:checked').forEach(cb => {
    const field  = cb.name.replace('vis_', '');
    const xInput = document.querySelector('[name="x_' + field + '"]');
    const yInput = document.querySelector('[name="y_' + field + '"]');
    if (!xInput || !yInput) return;

    const handle = document.createElement('div');
    handle.className      = 'drag-handle';
    handle.dataset.field  = field;
    handle.dataset.label  = field.replace(/_/g,' ');
    handle.style.left     = (parseInt(xInput.value) / scaleX) + 'px';
    handle.style.top      = (parseInt(yInput.value) / scaleY) + 'px';

    handle.addEventListener('mousedown',  e => startDrag(e, handle, xInput, yInput, scaleX, scaleY));
    handle.addEventListener('touchstart', e => startDrag(e, handle, xInput, yInput, scaleX, scaleY), {passive: false});
    overlay.appendChild(handle);
  });
}

// ── Drag individual ───────────────────────────────────────────────────────────
function startDrag(e, handle, xInput, yInput, scaleX, scaleY) {
  e.preventDefault(); e.stopPropagation();
  isDragging = true;
  const isTouch = e.type === 'touchstart';
  const cx = isTouch ? e.touches[0].clientX : e.clientX;
  const cy = isTouch ? e.touches[0].clientY : e.clientY;
  handle.classList.add('dragging');
  dragState = {
    isGridDrag: false, handle, xInput, yInput, scaleX, scaleY, isTouch,
    startCX: cx, startCY: cy,
    origLeft: parseFloat(handle.style.left) || 0,
    origTop:  parseFloat(handle.style.top)  || 0,
  };
  document.addEventListener(isTouch ? 'touchmove' : 'mousemove', onDrag,  {passive: false});
  document.addEventListener(isTouch ? 'touchend'  : 'mouseup',   endDrag, {once: true});
}

// ── Drag de grilla completa ───────────────────────────────────────────────────
function startGridDrag(e, scaleX, scaleY) {
  e.preventDefault(); e.stopPropagation();
  isDragging = true;
  const isTouch = e.type === 'touchstart';
  const cx = isTouch ? e.touches[0].clientX : e.clientX;
  const cy = isTouch ? e.touches[0].clientY : e.clientY;
  const gh = e.currentTarget;
  gh.classList.add('dragging');

  // Guardar posiciones originales de todos los campos visibles
  const origPositions = {};
  document.querySelectorAll('input[name^="vis_"]:checked').forEach(cb => {
    const f = cb.name.replace('vis_', '');
    const xi = document.querySelector('[name="x_' + f + '"]');
    const yi = document.querySelector('[name="y_' + f + '"]');
    if (xi && yi) origPositions[f] = { x: parseInt(xi.value), y: parseInt(yi.value) };
  });

  dragState = { isGridDrag: true, handle: gh, scaleX, scaleY, isTouch, startCX: cx, startCY: cy, origPositions };
  document.addEventListener(isTouch ? 'touchmove' : 'mousemove', onDrag,  {passive: false});
  document.addEventListener(isTouch ? 'touchend'  : 'mouseup',   endDrag, {once: true});
}

function onDrag(e) {
  if (!dragState) return;
  e.preventDefault();
  const { scaleX, scaleY, isTouch, startCX, startCY } = dragState;
  const cx = isTouch ? e.touches[0].clientX : e.clientX;
  const cy = isTouch ? e.touches[0].clientY : e.clientY;
  const overlay = document.getElementById('drag-overlay');

  if (dragState.isGridDrag) {
    const dx = (cx - startCX) * scaleX;
    const dy = (cy - startCY) * scaleY;
    document.querySelectorAll('input[name^="vis_"]:checked').forEach(cb => {
      const f = cb.name.replace('vis_', '');
      if (!dragState.origPositions[f]) return;
      const xi = document.querySelector('[name="x_' + f + '"]');
      const yi = document.querySelector('[name="y_' + f + '"]');
      if (!xi || !yi) return;
      xi.value = Math.max(0, Math.round(dragState.origPositions[f].x + dx));
      yi.value = Math.max(0, Math.round(dragState.origPositions[f].y + dy));
      const h = overlay.querySelector('[data-field="' + f + '"]');
      if (h) { h.style.left = (parseInt(xi.value) / scaleX) + 'px'; h.style.top = (parseInt(yi.value) / scaleY) + 'px'; }
    });
    return;
  }

  const { handle, xInput, yInput, origLeft, origTop } = dragState;
  let newLeft = Math.max(0, Math.min(origLeft + (cx - startCX), overlay.offsetWidth));
  let newTop  = Math.max(0, Math.min(origTop  + (cy - startCY), overlay.offsetHeight));
  const snapped = snapPosition(newLeft, newTop, handle.dataset.field, overlay);
  newLeft = snapped.x; newTop = snapped.y;
  showGuides(snapped.guides, overlay);
  handle.style.left = newLeft + 'px';
  handle.style.top  = newTop  + 'px';
  xInput.value = Math.round(newLeft * scaleX);
  yInput.value = Math.round(newTop  * scaleY);
}

function endDrag() {
  if (!dragState) return;
  dragState.handle.classList.remove('dragging');
  const { isTouch } = dragState;
  document.removeEventListener(isTouch ? 'touchmove' : 'mousemove', onDrag);
  document.querySelectorAll('#drag-overlay .snap-guide').forEach(g => g.style.opacity = '0');
  dragState = null;
  isDragging = false;
  clearTimeout(previewTimer);
  previewTimer = setTimeout(loadPreview, 300);
}

function snapPosition(x, y, currentField, overlay) {
  const W = overlay.offsetWidth, H = overlay.offsetHeight;
  let sx = x, sy = y;
  const guides = [];
  const targetsX = [W / 2], targetsY = [H / 2];
  document.querySelectorAll('#drag-overlay .drag-handle').forEach(h => {
    if (h.dataset.field === currentField) return;
    targetsX.push(parseFloat(h.style.left));
    targetsY.push(parseFloat(h.style.top));
  });
  for (const tx of targetsX) {
    if (Math.abs(x - tx) <= SNAP_PX) { sx = tx; guides.push({type:'v', pct:tx/W*100, isCenter:Math.abs(tx-W/2)<1}); break; }
  }
  for (const ty of targetsY) {
    if (Math.abs(y - ty) <= SNAP_PX) { sy = ty; guides.push({type:'h', pct:ty/H*100, isCenter:Math.abs(ty-H/2)<1}); break; }
  }
  return { x: sx, y: sy, guides };
}

function showGuides(guides, overlay) {
  overlay.querySelectorAll('.snap-guide').forEach(g => g.style.opacity = '0');
  guides.forEach(g => {
    const id = 'sg_' + g.type + '_' + Math.round(g.pct * 10);
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('div');
      el.id = id; el.className = 'snap-guide snap-guide-' + g.type;
      if (g.isCenter) el.style.background = 'rgba(245,158,11,.5)';
      overlay.appendChild(el);
    }
    if (g.type === 'v') el.style.left = g.pct + '%'; else el.style.top = g.pct + '%';
    el.style.opacity = '1';
  });
}

window.addEventListener('resize', buildDragOverlay);
document.getElementById('design-form').addEventListener('input', triggerPreview);
loadPreview();

document.getElementById('design-form').addEventListener('submit', function(e) {
  const warnings = [];
  const visibles = document.querySelectorAll('input[name^="vis_"]:checked');
  if (visibles.length === 0) {
    e.preventDefault();
    document.getElementById('step2-warnings').innerHTML =
      '<div class="alert alert-danger"><?= addslashes(t('warn_no_visible')) ?></div>';
    document.getElementById('step2-warnings').classList.remove('d-none');
    return;
  }
  const callVis = document.querySelector('input[name="vis_CALL"]');
  if (!callVis || !callVis.checked) warnings.push('<?= addslashes(t('warn_no_call')) ?>');
  const dateRaw = '<?= addslashes($first['QSO_DATE'] ?? '') ?>';
  if (dateRaw && (dateRaw.length !== 8 || isNaN(Number(dateRaw))))
    warnings.push('<?= addslashes(t('warn_date_invalid', ['val' => $first['QSO_DATE'] ?? ''])) ?>');
  const coords = [];
  document.querySelectorAll('input[name^="vis_"]:checked').forEach(cb => {
    const f = cb.name.replace('vis_', '');
    coords.push({f, x: parseInt(document.querySelector('[name="x_'+f+'"]')?.value||'0'), y: parseInt(document.querySelector('[name="y_'+f+'"]')?.value||'0')});
  });
  let overlap = false;
  for (let i = 0; i < coords.length && !overlap; i++)
    for (let j = i+1; j < coords.length && !overlap; j++)
      if (Math.abs(coords[i].x-coords[j].x)<20 && Math.abs(coords[i].y-coords[j].y)<20) overlap = true;
  if (overlap) warnings.push('<?= addslashes(t('warn_overlap')) ?>');
  if (warnings.length > 0) {
    e.preventDefault();
    let html = warnings.map(w => '<div class="alert alert-warning py-2 small mb-2">'+w+'</div>').join('');
    html += '<button type="submit" class="btn btn-warning btn-sm"><?= addslashes(t('warn_continue')) ?></button>';
    const el = document.getElementById('step2-warnings');
    el.innerHTML = html; el.classList.remove('d-none'); el.scrollIntoView({behavior:'smooth'});
    el.querySelector('button').addEventListener('click', () => document.getElementById('design-form').submit());
  }
});
</script>

<?php /* ═══════════════════════════════ STEP 3 ══════════════════════════════ */ ?>
<?php elseif ($step === 3): ?>
<div class="row g-4">
<div class="col-lg-7">
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="bi bi-archive me-2"></i><?= t('generate_title') ?></div>
  <div class="card-body">
    <div class="alert alert-info mb-4" id="gen-info-banner">
      <i class="bi bi-info-circle me-2"></i>
      <?= t('generate_info_total', ['n' => $summary['total'] ?? 0]) ?>
      <div class="small mt-1 text-muted">
        <?= $summary['calls'] ?? 0 ?> <?= t('lang_switch_code') === 'en' ? 'unique callsigns' : 'indicativos distintos' ?>
        · <?= h($summary['bands'] ?? '') ?>
        · <?= h($summary['modes'] ?? '') ?>
      </div>
    </div>

    <!-- Selector de acción -->
    <div id="action-section">
      <p class="fw-semibold text-center mb-3"><?= t('action_choice') ?></p>
      <div class="d-grid gap-2">
        <button class="btn btn-warning btn-lg fw-semibold" onclick="generateBatch('zip')">
          <i class="bi bi-file-zip me-2"></i><?= t('action_zip') ?>
        </button>
        <button class="btn btn-primary btn-lg fw-semibold" onclick="generateBatch('email')">
          <i class="bi bi-envelope me-2"></i><?= t('action_email') ?>
        </button>
        <button class="btn btn-outline-secondary btn-lg fw-semibold" onclick="generateBatch('both')">
          <i class="bi bi-magic me-2"></i><?= t('action_both') ?>
        </button>
      </div>
    </div>

    <div id="gen-progress" class="d-none text-center py-3">
      <div class="spinner-border text-warning mb-2"></div>
      <div class="text-muted"><?= t('generating') ?></div>
    </div>

    <div id="gen-done" class="d-none">
      <a id="dl-link" href="#" class="btn btn-success btn-lg w-100 fw-semibold mb-3 d-none" download>
        <i class="bi bi-download me-2"></i><span id="dl-label"></span>
      </a>
      <a href="<?= APP_URL ?>/generate.php" class="btn btn-outline-secondary w-100 mt-2">
        <i class="bi bi-plus me-1"></i>Nueva generación
      </a>
    </div>

    <!-- Email section -->
    <div id="email-section" class="d-none mt-4 border-top pt-4">
      <h6 class="fw-semibold"><i class="bi bi-envelope me-2 text-primary"></i><?= t('email_section') ?></h6>
      <p class="small text-muted"><?= t('email_help') ?></p>
      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= t('email_from') ?></label>
        <input type="email" id="email-from" class="form-control form-control-sm"
               value="<?= h(usuario_actual()['email'] ?? '') ?>" placeholder="your@email.com">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= t('email_subject') ?></label>
        <input type="text" id="email-subject" class="form-control form-control-sm"
               value="<?= h(t('email_subject_def', ['callsign' => usuario_actual()['callsign'] ?? 'N0CALL'])) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= t('email_body_label') ?></label>
        <textarea id="email-body" class="form-control form-control-sm" rows="5"><?= h(t('email_body_def', [
            'name'     => '{name}',
            'date'     => '{date}',
            'band'     => '{band}',
            'mode'     => '{mode}',
            'callsign' => usuario_actual()['callsign'] ?? 'N0CALL',
        ])) ?></textarea>
        <div class="form-text small"><?= t('lang_switch_code') === 'en' ? 'Use {name}, {date}, {band}, {mode} as placeholders.' : 'Usá {name}, {date}, {band}, {mode} como variables.' ?></div>
      </div>
      <div id="email-contacts" class="mb-3"></div>
      <button class="btn btn-primary w-100" id="btn-send-emails" onclick="sendEmails()">
        <i class="bi bi-send me-1"></i><?= t('email_send_btn') ?>
      </button>
      <div id="email-result" class="mt-2"></div>
    </div>
  </div>
</div>
</div>

<div class="col-lg-5">
  <div class="card shadow-sm">
    <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
      <span><i class="bi bi-list-ul me-1"></i>QSO list</span>
      <span id="qso-sel-count" class="badge bg-secondary"><?= count($qsos) ?> <?= t('lang_switch_code') === 'en' ? 'selected' : 'seleccionados' ?></span>
    </div>
    <div class="card-body p-2 border-bottom">
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectQsos('all')"><?= t('select_all') ?></button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectQsos('none')"><?= t('select_none') ?></button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectQsos('invert')"><?= t('select_invert') ?></button>
      </div>
    </div>
    <div class="card-body p-0" style="max-height:340px;overflow-y:auto">
      <table class="table table-sm mb-0 small" id="qso-list-table">
        <thead><tr><th></th><th>Call</th><th>Date</th><th>Band</th><th>Mode</th></tr></thead>
        <tbody>
          <?php foreach ($qsos as $idx => $q): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input qso-chk" data-call="<?= h($q['CALL'] ?? '') ?>" data-idx="<?= $idx ?>" checked onchange="updateQsoCount()"></td>
            <td class="fw-semibold"><?= h($q['CALL'] ?? '') ?></td>
            <td><?= h(fmt_date_adif($q['QSO_DATE'] ?? '')) ?></td>
            <td><?= h($q['BAND'] ?? '') ?></td>
            <td><?= h($q['MODE'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-2">
    <a href="<?= APP_URL ?>/generate.php?step=2" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i><?= t('step_back') ?>
    </a>
  </div>
</div>
</div>

<script>
let batchUuid = null;
let batchMode = 'zip';
const qsos = <?= json_encode(array_map(fn($q) => ['call' => $q['CALL'] ?? '', 'name' => $q['NAME'] ?? '', 'email' => ''], $qsos)) ?>;
const labelSelected = '<?= t('lang_switch_code') === 'en' ? 'selected' : 'seleccionados' ?>';
const emailBodyDef   = <?= json_encode(t('email_body_def',   ['name'=>'{name}','date'=>'{date}','band'=>'{band}','mode'=>'{mode}','callsign'=>usuario_actual()['callsign'] ?? 'N0CALL'])) ?>;
const emailBodyMulti = <?= json_encode(t('email_body_multi', ['name'=>'{name}','n'=>'{n}','callsign'=>usuario_actual()['callsign'] ?? 'N0CALL'])) ?>;

function getSelectedIndices() {
  const indices = [];
  document.querySelectorAll('.qso-chk:checked').forEach(chk => indices.push(parseInt(chk.dataset.idx)));
  return indices;
}

function getSelectedCalls() {
  const calls = new Set();
  document.querySelectorAll('.qso-chk:checked').forEach(chk => calls.add(chk.dataset.call));
  return [...calls];
}

function updateQsoCount() {
  const n = document.querySelectorAll('.qso-chk:checked').length;
  document.getElementById('qso-sel-count').textContent = n + ' ' + labelSelected;
}

function selectQsos(mode) {
  document.querySelectorAll('.qso-chk').forEach(c => {
    if (mode === 'all')         c.checked = true;
    else if (mode === 'none')   c.checked = false;
    else if (mode === 'invert') c.checked = !c.checked;
  });
  updateQsoCount();
}

function generateBatch(mode) {
  const selectedIndices = getSelectedIndices();
  if (selectedIndices.length === 0) {
    alert('<?= addslashes(t('warn_no_qsos')) ?>');
    return;
  }
  batchMode = mode || 'zip';
  document.getElementById('action-section').classList.add('d-none');
  document.getElementById('gen-progress').classList.remove('d-none');
  fetch('<?= APP_URL ?>/api/generate.php', {
    method: 'POST',
    headers: {'X-CSRF-Token': '<?= csrf_token() ?>','Content-Type':'application/json'},
    body: JSON.stringify({action: 'generate', indices: selectedIndices})
  })
  .then(r => r.json())
  .then(d => {
    document.getElementById('gen-progress').classList.add('d-none');
    if (d.ok) {
      // Actualizar banner con el count real generado (no el total del ADIF)
      const banner = document.getElementById('gen-info-banner');
      if (banner) {
        banner.className = 'alert alert-success mb-4';
        banner.innerHTML = '<i class="bi bi-check-circle me-2"></i><?= addslashes(t('generated_ok', ['n' => '{n}'])) ?>'.replace('{n}', d.n);
      }
      batchUuid = d.uuid;
      const dlLink  = document.getElementById('dl-link');
      const dlLabel = document.getElementById('dl-label');
      dlLink.href   = '<?= APP_URL ?>/download.php?uuid=' + d.uuid;
      dlLabel.textContent = '<?= addslashes(t('download_btn', ['n' => '{n}'])) ?>'.replace('{n}', d.n);

      document.getElementById('gen-done').classList.remove('d-none');

      if (batchMode === 'zip' || batchMode === 'both') {
        dlLink.classList.remove('d-none');
      }
      if (batchMode === 'email' || batchMode === 'both') {
        buildEmailContacts(getSelectedCalls());
        document.getElementById('email-section').classList.remove('d-none');
      }
    } else {
      document.getElementById('action-section').classList.remove('d-none');
      alert(d.error || '<?= addslashes(t('err_generate')) ?>');
    }
  });
}

function buildEmailContacts(selectedCalls) {
  const el = document.getElementById('email-contacts');
  const seen = {};
  const rows = [];
  const callCount = {};
  qsos.forEach(q => {
    if (!selectedCalls.includes(q.call)) return;
    callCount[q.call] = (callCount[q.call] || 0) + 1;
    if (seen[q.call]) return;
    seen[q.call] = 1;
    rows.push(q);
  });

  // Adapt email body template based on whether any callsign has multiple cards
  const hasMulti = Object.values(callCount).some(n => n > 1);
  const bodyEl = document.getElementById('email-body');
  if (bodyEl && !bodyEl.dataset.userEdited) {
    bodyEl.value = hasMulti ? emailBodyMulti : emailBodyDef;
  }
  bodyEl && bodyEl.addEventListener('input', () => { bodyEl.dataset.userEdited = '1'; }, {once: true});

  let html = '<div class="table-responsive"><table class="table table-sm small mb-0"><thead><tr><th>Callsign</th><th><?= t('lang_switch_code') === 'en' ? 'Cards' : 'Tarjetas' ?></th><th>Email</th></tr></thead><tbody>';
  rows.forEach(q => {
    const n = callCount[q.call] || 1;
    html += '<tr>';
    html += '<td class="fw-semibold">' + q.call + '</td>';
    html += '<td class="text-muted">' + n + '</td>';
    html += '<td><input type="email" class="form-control form-control-sm email-dest" data-call="' + q.call + '" data-name="' + (q.name||q.call) + '" placeholder="(skip)"></td></tr>';
  });
  html += '</tbody></table></div>';
  el.innerHTML = html;
}

function sendEmails() {
  const from    = document.getElementById('email-from').value;
  const subject = document.getElementById('email-subject').value;
  const bodyTpl = document.getElementById('email-body').value;
  const dests = [];
  document.querySelectorAll('#email-contacts tbody tr').forEach(row => {
    const inp = row.querySelector('.email-dest');
    if (inp && inp.value) {
      dests.push({call: inp.dataset.call, name: inp.dataset.name, email: inp.value});
    }
  });
  if (!from || !dests.length) return;
  document.getElementById('btn-send-emails').disabled = true;
  document.getElementById('btn-send-emails').textContent = '<?= addslashes(t('email_sending')) ?>';
  fetch('<?= APP_URL ?>/api/generate.php', {
    method: 'POST',
    headers: {'X-CSRF-Token': '<?= csrf_token() ?>','Content-Type':'application/json'},
    body: JSON.stringify({action: 'send_emails', uuid: batchUuid, from, subject, body_tpl: bodyTpl, dests})
  })
  .then(r => r.json())
  .then(d => {
    document.getElementById('btn-send-emails').disabled = false;
    document.getElementById('btn-send-emails').innerHTML = '<i class="bi bi-send me-1"></i><?= addslashes(t('email_send_btn')) ?>';
    document.getElementById('email-result').innerHTML = d.ok
      ? '<div class="alert alert-success small py-2 mt-2">' + d.message + '</div>'
      : '<div class="alert alert-danger small py-2 mt-2">' + (d.error||'Error') + '</div>';
  });
}

// Sacar ?step=3 de la URL — un reload vuelve a step 1
if (window.history && window.history.replaceState) {
  window.history.replaceState({}, '', '<?= APP_URL ?>/generate.php');
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
