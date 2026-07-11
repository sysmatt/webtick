# WebTick

A small single-page web UI for printing ESC/POS receipts/tickets on a CUPS-connected
thermal printer, with a live receipt preview and a command-line preview of the
underlying print call. It's a thin PHP front end over the
[`sysmatt.escpos.ticket.print`](#requirements) Python tool.

## Features

- Live receipt preview (header, title, body, trailer, QR code, cut indicator) as you type
- Header/title/body text can render either as native ESC/POS text or as TTF-rendered
  raster images (for custom fonts and sizes)
- Logo image and multiple inline `--image` attachments via drag-and-drop
- Printer queue autodetected from CUPS (`lpstat -a`)
- Configurable pixel width / graphics implementation / cut / beep / font defaults
  per deployment via `webtick.ini` (see [Configuration](#configuration))
- Shows the exact command that will be run, plus full stdout/stderr after printing
- Optional session-based login via `simplewebauth` (see [Authentication](#authentication))

## Requirements

- PHP with `exec()` and `proc_open()` enabled, served by a webserver (Apache, Nginx+PHP-FPM, etc.)
- CUPS with `lpstat` available on the host, and at least one configured print queue
- Python 3 with [Pillow](https://pypi.org/project/Pillow/) and the
  `sysmatt.escpos.ticket.print` tool installed
  (referenced via `python_bin` / `script_path` in config — a venv works fine)
- TrueType fonts on disk for any entries you list under `[fonts]` in `webtick.ini`
- Optional: a `simplewebauth` deployment if you enable authentication (see [Authentication](#authentication))

## Installation

1. Deploy `index.php`, `print.php`, and `lib/` to your webserver's document root
   (e.g. `/var/www/webtick/`).
2. Copy `webtick.ini.example` to `webtick.ini` **one directory above** the project
   root — i.e. outside the web-accessible document root — and edit it for your
   printer and host:

   ```
   /var/www/webtick.ini          <- config, NOT web-accessible
   /var/www/webtick/index.php
   /var/www/webtick/print.php
   /var/www/webtick/lib/
   ```

3. Make sure the webserver user can run `lpstat`, the configured `python_bin`,
   and reach the configured printer queue.
4. (Optional) Set up `simplewebauth` and enable `[auth] enabled = true` — see
   [Authentication](#authentication).

If `webtick.ini` is missing, WebTick falls back to built-in defaults (the same
values this project originally shipped with), so it will still run — just
against whatever paths/printer those defaults assume.

## Configuration

All settings are optional; anything you don't set falls back to a default.
See `webtick.ini.example` for a fully-commented copy of this.

| Section | Key | Meaning | Default |
|---|---|---|---|
| `[auth]` | `enabled` | Protect `index.php` and `print.php` with [simplewebauth](#authentication) | `false` |
| `[auth]` | `simplewebauth_dir` | Filesystem path to the simplewebauth deployment (must be web-accessible — see [Authentication](#authentication)) | `<sibling of webtick/>/simplewebauth` |
| `[tool]` | `python_bin` | Python interpreter used to run the print tool (point at a venv if needed) | `/usr/bin/python3` |
| `[tool]` | `script_path` | Path to the `sysmatt.escpos.ticket.print` script | `/opt/sage/local/platform/scripts/sysmatt.escpos.ticket.print` |
| `[printer]` | `default_queue` | CUPS queue preselected in the UI (falls back to a queue name containing "citizen", then the first available queue) | `CITIZEN_CT_S310_clocky4` |
| `[printer]` | `widths` | Comma-separated list of selectable pixel widths | `384,576,832` |
| `[printer]` | `default_width` | Preselected pixel width | `576` |
| `[printer]` | `impls` | Comma-separated list of selectable graphics implementations | `bitImageRaster,graphics,bitImageColumn` |
| `[printer]` | `default_impl` | Preselected graphics implementation | `bitImageRaster` |
| `[printer]` | `default_cut` | Initial state of the "Cut paper" toggle | `true` |
| `[printer]` | `default_beep` | Initial state of the "Beep" toggle | `false` |
| `[rendering]` | `new_text_render` | Preselect the new Pillow `textbbox`-based renderer over the legacy one | `false` |
| `[fonts]` | *(any key)* `= path` | TTF font choices offered for Header/Title/Body. UI label is derived from the key (`liberation-sans-bold` → "Liberation Sans Bold"). Add/remove entries freely | Liberation + Ubuntu families |
| `[font_defaults]` | `header`, `title`, `body` | Which `[fonts]` key (or blank for native ESC/POS) is preselected for each field | blank (native) |
| `[font_sizes]` | `{header,title,body}_min` / `_max` / `_default` | Range and default value of each size slider (only meaningful when a TTF font is selected for that field) | header 1–60 (4), title 1–60 (2), body 1–60 (1) |

Only fonts and printer widths/impls listed in `webtick.ini` are ever passed to
the underlying tool — `print.php` validates every submitted value against
these configured whitelists before building the command line.

## Authentication

WebTick can be protected with `simplewebauth`, a drop-in session-based login
for small PHP tools (see its own README for setup/user-management details).
It's off by default; set `[auth] enabled = true` in `webtick.ini` to turn it on.

**Setup:**

1. Deploy a copy of simplewebauth as a sibling of the `webtick/` directory
   itself — e.g. `/var/www/html/simplewebauth` next to `/var/www/html/webtick`
   (the default `webtick.ini` expects exactly this layout), or point
   `simplewebauth_dir` at a shared install elsewhere on the host. Either way,
   **that directory must be web-accessible** — simplewebauth's login redirect
   is computed against `$_SERVER['DOCUMENT_ROOT']`, so it can't live outside
   the docroot the way `webtick.ini` itself does.
2. Follow simplewebauth's own README to add users (`authctl add <username>`)
   and, if you want, configure Apache/Nginx to block direct access to its
   management scripts.
3. Set `[auth] enabled = true` in `webtick.ini`.

Once enabled, both `index.php` and `print.php` require a valid session —
unauthenticated page loads are redirected to the login page and returned to
WebTick afterward. The header shows the logged-in username and a sign-out
link.

**Note on `print.php`:** it's a JSON endpoint called via `fetch()`, not a
page load. If a session expires mid-use, simplewebauth's default behavior
(HTTP redirect to the login page) will be silently followed by `fetch()`,
and the resulting HTML response will fail to parse as JSON — the UI shows a
generic print-failed error rather than "please log in again." Given
simplewebauth's 8-hour sliding session, this is rare in practice, and a page
reload immediately shows the login screen.

## Usage

Open `index.php` in a browser, fill in the ticket content, adjust hardware/finishing
options as needed, and click **Print Ticket**. The preview panel shows a live
receipt mockup and, after printing, the full stdout/stderr from the print tool.

## How it works

- `index.php` renders the form and a live JS preview; submitting builds a
  `FormData` payload (including any uploaded logo/images) and POSTs it to `print.php`.
- `print.php` re-validates and sanitizes every field server-side (whitelisted
  fonts/impls/widths, `escapeshellarg()` throughout, printer name regex, image
  uploads checked with `getimagesize()`), builds the `sysmatt.escpos.ticket.print`
  command line, and runs it via `proc_open()`, streaming the ticket body over stdin.
- The JSON response (`success`, `exit_code`, `stdout`, `stderr`) is shown in the UI.

## File layout

```
index.php               Form UI + live preview + AJAX submit
print.php               Server-side validation and print execution
lib/config.php          webtick.ini loader (with built-in fallback defaults)
webtick.ini.example     Documented config template — copy to ../webtick.ini
```

## Security notes

- `webtick.ini` is expected to live outside the document root; don't move it
  inside a web-accessible directory.
- As defense in depth, also block `*.ini` files at the webserver regardless of
  where they live — this catches any config accidentally placed inside the
  docroot (yours or another app's). Nginx:

  ```nginx
  location ~* \.ini$ {
      deny all;
      return 404;
  }
  ```

  Apache equivalent, in a `.conf` or `.htaccess`:

  ```apache
  <FilesMatch "\.ini$">
      Require all denied
  </FilesMatch>
  ```
- Font paths, pixel widths, and graphics implementations are only ever chosen
  from the configured whitelists — arbitrary user input is never used as a
  file path or shell argument without `escapeshellarg()`.
- Uploaded images are validated with `getimagesize()` and an extension
  whitelist before being passed to the print tool, and temp files are deleted
  after each request.

## License

GPLv3 — see [LICENSE](LICENSE).
