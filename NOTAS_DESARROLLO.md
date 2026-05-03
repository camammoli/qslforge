# QSLforge — Notas de desarrollo

## v0.1.0 — Sesión 2026-04-30 (Claude Code)

### Contexto
Generador web gratuito de tarjetas QSL para radioaficionados. El usuario sube un log ADIF y una imagen de fondo, diseña visualmente la tarjeta y descarga el lote en ZIP o lo envía por email directamente a cada indicativo.

Nació de la ausencia de herramientas web para esto (todo lo existente era Python CLI). Diseñado desde el día 1 para uso público multi-usuario en `mammoli.ar/qslforge/`.

Stack: PHP 8 + GD + MySQL, Bootstrap 5.3, i18n propio (EN/ES).

### Lo que se hizo — v0.1 (scaffolding)

**Estructura inicial:**
- `generate.php` — flujo de 3 pasos en una sola página con `$_SESSION`
- `includes/adif.php` — parser ADIF puro PHP con regex sobre tags `<FIELD:LEN>`
- `includes/card_gen.php` — `generate_card()`, `generate_batch()`, `build_zip()`, `generate_preview()`
- `includes/i18n.php` + `includes/i18n/en.php` + `es.php` — sistema de traducciones propio
- `api/preview.php` — AJAX: genera preview de primera QSO, devuelve URL
- `api/generate.php` — AJAX: genera batch completo + envío emails
- `download.php` — sirve el ZIP por UUID de sesión o DB
- `setup.php?key=qslforge_setup_2026` — crea tablas (usuarios, templates, lotes)
- `config.local.php` — credenciales BD, excluido del repo desde el inicio

**DB:** `mammoli_qslforge` (separada de `mammoli_qsl` que usa el QSL Manager)
**Tablas:** `usuarios`, `templates`, `lotes`

**Campos ADIF soportados:**
CALL, QSO_DATE, TIME_ON, BAND, FREQ, MODE, RST_SENT, RST_RCVD, NAME, QTH, GRIDSQUARE, OPERATOR, COMMENT, CUSTOM (texto fijo)

**Fixes iniciales:**
- DB name corregido (`mammoli_qslforge` no `mammoli_qsl`)
- Setup.php: manejo robusto de errores, intenta crear DB automáticamente
- Fix texto invisible en cards (color de fuente sobre imagen oscura)
- Fix composite PNG sobre blanco al guardar como JPEG

**Commits:**
```
6b4becb — QSLforge v0.1.0 — initial commit
99e11c0 — fix: DB name → mammoli_qslforge
1e8efe6 — fix: setup.php — manejo robusto de errores
4505421 — fix: texto invisible en cards + admin creation en setup
7b81372 — fix: composite PNG over white when saving as JPEG; fix preview extension
```

---

## v0.2.0 — Sesión 2026-04-30 (Claude Code)

### Lo que se hizo — v0.2 (flujo rediseñado + beta pública)

**Flujo rediseñado:**
- Paso 1: upload ADIF + imagen → validaciones más estrictas
- Paso 2: diseñador visual (posición/tamaño/color de cada campo)
- Paso 3: selección de QSOs a incluir en el lote (checkbox por indicativo)
- Fix: session reset al recargar el paso 3

**Sistema de bug report:**
- `includes/bug_report.php` — formulario flotante en todas las páginas

**Validaciones:**
- Tipo MIME real de la imagen (no solo extensión)
- Tamaño máximo upload
- Feedback visual de errores

**i18n:**
- Sistema propio via arrays PHP + `detect_lang()` + cookie
- EN/ES completos en `includes/i18n/`

**Beta pública:**
- Banner informativo en `index.php` ("versión beta pública")
- Fix: condición de idioma invertida en banner

**Seguridad:**
- Credenciales BD movidas a `config.local.php` (excluido del repo)
- `config.local.example.php` como plantilla

**Documentación pública:**
- `README.md` — descripción, instalación, capturas, roadmap
- `LICENSE` — MIT
- `.gitignore` adecuado (excluye `config.local.php`, `output/`, `uploads/`)

**Commits:**
```
1496491 — QSLforge v0.2: flujo rediseñado, bug report, validaciones, i18n
d7a3b03 — QSLforge: selección de QSOs en step 3, fix session reset al recargar
a955ce7 — QSLforge: banner beta pública en home
7c832fb — fix: condición de idioma invertida en banner beta
91df69d — Mover credenciales de BD a config.local.php
279ec4f — Documentación pública: README, LICENSE MIT, config.local.example.php
```

---

## v0.3.0 — Sesión 2026-05-01 (Claude Code)

### Lo que se hizo — editor visual drag & drop + seguridad

**Editor visual (paso 2):**
- Drag & drop de campos sobre la imagen de fondo (JavaScript puro)
- Snapping magnético en step 2 (alineación a grilla configurable)
- Handles pequeños (8px) para mover campos, con hint visual al pasar el mouse
- Grid layout presets: distribución automática de campos en grilla
- Overlay de grilla SVG en el designer (toggle show/hide)
- Renderizado de líneas de grilla en la card final (GD)

**Fixes del designer:**
- Fix múltiples tarjetas por indicativo (generación de una card por QSO, no por CALL único)
- Fix filtro: filtrar por índice de QSO, no por indicativo (permite duplicados)
- Texto del banner: conteo real de QSOs seleccionados

**Email:**
- Fix: sección de email no se mostraba en ciertos estados de sesión
- Fix: reemplazar `{n}` en `body_tpl` cuando el usuario usa plantilla personalizada

**Seguridad:**
- Validación de contenido ADIF (no solo extensión del archivo)
- Sanitización de imagen subida con GD antes de usar en cards
- Log de seguridad (`security_log`) para eventos de validación

**Commits:**
```
950a7e6 — Editor visual: drag & drop + snapping magnético en step 2
2ef4d14 — fix: múltiples tarjetas por indicativo + banner con count real
242b432 — fix: filtro por índice de QSO (no por indicativo)
a9ef80a — feat(designer): small handles, drag hint, grid layout presets
1974c3c — fix: email section not shown + tighten upload validation
c4bb583 — security: validate ADIF content, GD sanitize image, add security log
5cf787d — fix(email): replace {n} in body_tpl when user provides custom template
7a43295 — feat(designer): draw grid overlay with SVG, toggle show/hide
ab38723 — feat: draw grid lines on final card (GD rendering)
```

---

## v0.4.0 — Sesión 2026-05-02 (Claude Code)

### Lo que se hizo — modo tabla QSL classic

**QSL Classic (modo tabla):**
- Nuevo modo de layout: tabla con celdas de colores al estilo CE8TDO
- Tabla clásica con columnas BAND/FREQ/MODE/DATE/TIME/RST
- Celdas coloreadas con color de fondo configurable por celda
- Transparencia configurable para la tabla sobre la imagen de fondo
- Selector de posición de la tabla (esquinas + centro)
- Presets de color: paleta predefinida de combinaciones populares

**Fixes del modo tabla:**
- Fix: celdas vacías en table_mode (el contenido no se pasaba al renderer GD)
- Fix: handles de tabla visibles cuando no corresponden (z-index)
- Fix: `grid_mode` e inputs de grilla estaban fuera del `design-form` (no se enviaban)

**Commits:**
```
e13997e — feat: QSL classic table mode with CE8TDO-style colored table
e79f136 — fix(qsl_classic): table cells empty + table-owned handles still visible
61f1240 — fix: grid_mode e inputs de grilla estaban fuera del design-form
0f806c7 — feat: presets de color + selector de posición + transparencia para tabla QSL classic
```

---

## Estado actual — 2026-05-03

- **GitHub:** `camammoli/qslforge` — privado, sincronizado con local (rama `main`)
- **Producción:** `https://mammoli.ar/qslforge/` — HTTP 200, funcionando
- **DB:** `mammoli_qslforge` en cPanel de mammoli.ar
- **Versión en producción:** v0.4.0 (último commit `0f806c7`)

### Pendientes conocidos
- Subir fuentes TTF al servidor (`assets/fonts/`) para tipografías adicionales
- `account/history.php` — historial de lotes por usuario (estructura de tabla existe)
- `admin/index.php` — estadísticas de uso
- Preview drag & drop mejorado (v2: mover campos con mouse sobre la imagen en tiempo real)
- Decidir cuándo hacer el repo público (open source)
