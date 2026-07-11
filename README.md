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

## Requirements

- PHP with `exec()` and `proc_open()` enabled, served by a webserver (Apache, Nginx+PHP-FPM, etc.)
- CUPS with `lpstat` available on the host, and at least one configured print queue
- Python 3 with [Pillow](https://pypi.org/project/Pillow/) and the
  [`sysmatt.escpos.ticket.print`](https://github.com/sysmatt) tool installed
  (referenced via `python_bin` / `script_path` in config — a venv works fine)
- TrueType fonts on disk for any entries you list under `[fonts]` in `webtick.ini`

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

If `webtick.ini` is missing, WebTick falls back to built-in defaults (the same
values this project originally shipped with), so it will still run — just
against whatever paths/printer those defaults assume.

## Configuration

All settings are optional; anything you don't set falls back to a default.
See `webtick.ini.example` for a fully-commented copy of this.

| Section | Key | Meaning | Default |
|---|---|---|---|
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
index.php              Form UI + live preview + AJAX submit
print.php              Server-side validation and print execution
lib/config.php          webtick.ini loader (with built-in fallback defaults)
webtick.ini.example     Documented config template — copy to ../webtick.ini
```

## Security notes

- `webtick.ini` is expected to live outside the document root; don't move it
  inside a web-accessible directory.
- Font paths, pixel widths, and graphics implementations are only ever chosen
  from the configured whitelists — arbitrary user input is never used as a
  file path or shell argument without `escapeshellarg()`.
- Uploaded images are validated with `getimagesize()` and an extension
  whitelist before being passed to the print tool, and temp files are deleted
  after each request.

## License

GPLv3 — see [LICENSE](LICENSE).
