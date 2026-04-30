# QSLforge

**Free QSL card generator for amateur radio operators.**

Upload your ADIF log, design your card, download a ZIP or send each card directly by email to every OM — no account required.

Live at **[mammoli.ar/qslforge](https://mammoli.ar/qslforge)**

> **Public beta** — fonts, layout options, email delivery and more improvements are on the way.

---

## Features

- **Any ADIF log** — works with WSJT-X, Log4OM, CQRLOG, DXKeeper, Ham Radio Deluxe and any logger that exports ADIF
- **Visual card designer** — upload your artwork, drag fields to exact pixel positions, choose font, size, color and alignment per field
- **Live preview** — card updates as you adjust settings, rendered server-side with FreeType
- **Selective generation** — check/uncheck individual QSOs before generating; bulk select with All / None / Invert
- **ZIP download** — all cards packed in a single ZIP, files cleaned up after download
- **Email delivery** — send each card as an attachment directly to the OM's email address
- **Editable email body** — customize the message with `{name}`, `{date}`, `{band}`, `{mode}` placeholders
- **Save templates** — registered users can save and reuse card designs
- **Bilingual** — English and Spanish (ES/EN switcher in navbar and footer)
- **No tracking, no ads** — open source, self-hostable

---

## Requirements

| Component | Version |
|---|---|
| PHP | 8.0 or higher |
| GD extension | with FreeType support (`imagettftext`) |
| ZipArchive | bundled with PHP |
| MySQL / MariaDB | 5.7 / 10.3 or higher |
| Web server | Apache (`.htaccess` / `mod_rewrite`) or Nginx |

Check your server's GD capabilities:
```php
<?php print_r(gd_info()); ?>
```
Look for `FreeType Support => true`.

---

## Installation

### 1. Clone the repo

```bash
git clone https://github.com/camammoli/qslforge.git
cd qslforge
```

### 2. Create the database

```sql
CREATE DATABASE qslforge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'qslforge'@'localhost' IDENTIFIED BY 'yourpassword';
GRANT ALL PRIVILEGES ON qslforge.* TO 'qslforge'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Configure credentials

Copy the example config and fill in your values:

```bash
cp config.local.example.php config.local.php
```

Edit `config.local.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'qslforge');
define('DB_USER', 'qslforge');
define('DB_PASS', 'yourpassword');
```

`config.local.php` is listed in `.gitignore` and will never be committed.

### 4. Add TTF fonts

Place your `.ttf` font files in `assets/fonts/` and register them in `includes/funciones.php` → `allowed_fonts()` and `font_path()`.

Fonts known to work with shared hosting FreeType (static, non-variable):
- **Courier Prime** — [jster.net](https://jster.net/font/courier-prime)
- **Roboto** (2017 static release) — [GitHub releases](https://github.com/googlefonts/roboto/releases/tag/v2.138)
- Liberation Sans, DejaVu Sans — available as system packages (`fonts-liberation`, `fonts-dejavu`)

Variable fonts (`.ttf` exported from newer Google Fonts) are **not** compatible with older FreeType builds common on shared hosting.

### 5. Run the setup wizard

Open `https://yourdomain.com/qslforge/setup.php?key=qslforge_setup_2026` in your browser.

The wizard will:
- Verify PHP/GD/ZipArchive requirements
- Create all database tables
- Let you create the first admin account

**Delete or restrict `setup.php` after running it.**

### 6. Configure Apache (optional)

An `.htaccess` is included for clean URLs and security headers. Make sure `mod_rewrite` is enabled.

---

## Configuration reference

All runtime settings live in `config.php`. Override credentials in `config.local.php`.

| Constant | Default | Description |
|---|---|---|
| `APP_URL` | `/qslforge` | URL prefix — change if installed at root |
| `MAX_ADIF_MB` | `10` | Maximum ADIF file size in MB |
| `MAX_IMG_MB` | `20` | Maximum background image size in MB |
| `OUTPUT_TTL` | `14400` | Seconds before generated ZIPs are purged |
| `UPLOAD_DIR` | `/tmp/qslf_*/uploads/` | Temp upload directory (outside public_html) |
| `OUTPUT_DIR` | `/tmp/qslf_*/output/` | Temp output directory (outside public_html) |

Temp files are placed outside the web root by default (`sys_get_temp_dir()`). Generated ZIPs are deleted immediately after download.

---

## Project structure

```
qslforge/
├── account/          # Login, register, account management
├── admin/            # Admin panel (user list)
├── api/
│   ├── generate.php  # Batch card generation + email sending
│   ├── preview.php   # Single-card preview (returns base64)
│   └── bug_report.php
├── assets/
│   ├── fonts/        # TTF fonts (not included — see Installation)
│   └── js/app.js
├── includes/
│   ├── adif.php      # ADIF parser
│   ├── card_gen.php  # GD card renderer
│   ├── funciones.php # Helpers, font registry, date formatting
│   ├── i18n/
│   │   ├── es.php
│   │   └── en.php
│   ├── mailer.php    # Email with attachment via mail()
│   ├── header.php
│   └── footer.php
├── config.php        # App settings (no credentials)
├── config.local.php  # DB credentials — gitignored, create from example
├── generate.php      # Main wizard (steps 1–3)
├── download.php      # ZIP delivery + cleanup
├── index.php         # Landing page
└── setup.php         # One-time install wizard
```

---

## Email delivery

QSLforge uses PHP's native `mail()` function. Emails may land in spam without proper SPF/DKIM configuration on your mail server.

For reliable delivery, replace `includes/mailer.php` with a PHPMailer/SMTP implementation pointing to your mail provider.

---

## Contributing

Bug reports and pull requests are welcome. Use the in-app bug report button or open a GitHub issue.

---

## Author

**Carlos Ariel Mammoli — LU2MCA**
Ugarteche, Luján de Cuyo, Mendoza, Argentina

[mammoli.ar](https://mammoli.ar) · [lu2mca.mammoli.ar](https://mammoli.ar/lu2mca/) · [GitHub](https://github.com/camammoli)

---

## License

MIT — see [LICENSE](LICENSE)
