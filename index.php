<?php
// Dynamically fetch CUPS printers
$printers = [];
exec('lpstat -a 2>/dev/null', $lp_out);
foreach ($lp_out as $line) {
    if (preg_match('/^(\S+)\s+accepting/', $line, $m)) {
        $printers[] = $m[1];
    }
}
if (empty($printers)) {
    $printers = ['citizen-raw'];
}

$font_options = [
    ''                    => '— Native ESC/POS —',
    'liberation-sans'     => 'Liberation Sans',
    'liberation-sans-bold'=> 'Liberation Sans Bold',
    'liberation-mono'     => 'Liberation Mono',
    'liberation-mono-bold'=> 'Liberation Mono Bold',
    'liberation-serif'    => 'Liberation Serif',
    'liberation-serif-bold'=>'Liberation Serif Bold',
    'ubuntu'              => 'Ubuntu',
    'ubuntu-bold'         => 'Ubuntu Bold',
    'ubuntu-mono'         => 'Ubuntu Mono',
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WebTick</title>
<style>
:root {
    --bg:        #0d1117;
    --surface:   #161b22;
    --card:      #1c2128;
    --border:    #30363d;
    --border-hi: #484f58;
    --accent:    #58a6ff;
    --accent-dim:#1f6feb33;
    --green:     #3fb950;
    --red:       #f85149;
    --amber:     #e3b341;
    --text:      #e6edf3;
    --muted:     #8b949e;
    --faint:     #30363d;
    --receipt-bg:#fefcf7;
    --receipt-text:#1a1a1a;
    --receipt-sep:#c8c0b0;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    height: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    font-size: 14px;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
}

/* ── Header ────────────────────────────────────────────────── */
.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    height: 56px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-icon {
    width: 32px;
    height: 32px;
    background: var(--accent);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
}

.brand-name {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.3px;
    color: var(--text);
}

.brand-sub {
    font-size: 12px;
    color: var(--muted);
    margin-left: 4px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.printer-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 12px;
    color: var(--muted);
}

.printer-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 6px var(--green);
}

/* ── Main Layout ────────────────────────────────────────────── */
.app-main {
    display: grid;
    grid-template-columns: 420px 1fr;
    height: calc(100vh - 56px);
    overflow: hidden;
}

/* ── Control Panel ──────────────────────────────────────────── */
.control-panel {
    background: var(--surface);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.control-panel::-webkit-scrollbar { width: 6px; }
.control-panel::-webkit-scrollbar-track { background: transparent; }
.control-panel::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

.form-body {
    flex: 1;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* ── Form Sections ──────────────────────────────────────────── */
.form-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    cursor: pointer;
    user-select: none;
    border-bottom: 1px solid transparent;
    transition: background 0.15s;
}

.section-header:hover {
    background: rgba(255,255,255,0.03);
}

.section-header.open {
    border-bottom-color: var(--border);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 13px;
    color: var(--text);
}

.section-icon {
    font-size: 15px;
    opacity: 0.8;
}

.section-chevron {
    color: var(--muted);
    font-size: 11px;
    transition: transform 0.2s;
}

.section-header.open .section-chevron {
    transform: rotate(180deg);
}

.section-body {
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ── Fields ─────────────────────────────────────────────────── */
.field { display: flex; flex-direction: column; gap: 5px; }

.field label {
    font-size: 12px;
    font-weight: 500;
    color: var(--muted);
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}

.field-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

input[type=text],
input[type=number],
textarea,
select {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    padding: 7px 10px;
    font-size: 13px;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
    -webkit-appearance: none;
    appearance: none;
}

input[type=text]:focus,
input[type=number]:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-dim);
}

textarea {
    resize: vertical;
    min-height: 90px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    line-height: 1.5;
}

select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%238b949e'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 28px;
    cursor: pointer;
}

select option { background: #1c2128; }

input[type=number] { width: 100%; }

/* ── Toggle Switches ─────────────────────────────────────────── */
.toggle-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 13px !important;
    color: var(--text) !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    font-weight: 400 !important;
    padding: 6px 0;
}

.toggle-label input[type=checkbox] {
    display: none;
}

.toggle-switch {
    position: relative;
    width: 34px;
    height: 19px;
    flex-shrink: 0;
    background: var(--faint);
    border-radius: 10px;
    transition: background 0.2s;
    border: 1px solid var(--border-hi);
}

.toggle-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: var(--muted);
    transition: transform 0.2s, background 0.2s;
}

.toggle-label input:checked + .toggle-switch {
    background: var(--accent);
    border-color: var(--accent);
}

.toggle-label input:checked + .toggle-switch::after {
    transform: translateX(15px);
    background: white;
}

.toggle-text { font-size: 13px; color: var(--text); }

/* ── Size Slider ─────────────────────────────────────────────── */
.size-control {
    display: flex;
    align-items: center;
    gap: 8px;
}

.size-control input[type=range] {
    flex: 1;
    -webkit-appearance: none;
    appearance: none;
    height: 4px;
    background: var(--border-hi);
    border-radius: 2px;
    border: none;
    padding: 0;
    box-shadow: none;
}

.size-control input[type=range]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--accent);
    cursor: pointer;
    border: 2px solid var(--bg);
    box-shadow: 0 0 4px rgba(88,166,255,0.5);
}

.size-control input[type=range]:focus {
    outline: none;
    box-shadow: none;
    border: none;
}

.size-val {
    width: 24px;
    text-align: center;
    font-weight: 600;
    color: var(--accent);
    font-size: 13px;
    flex-shrink: 0;
}

/* ── Actions Bar ─────────────────────────────────────────────── */
.actions-bar {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-print {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    background: var(--accent);
    color: #0d1117;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s, opacity 0.2s;
    letter-spacing: -0.2px;
}

.btn-print:hover:not(:disabled) {
    background: #79b8ff;
}

.btn-print:active:not(:disabled) {
    transform: scale(0.98);
}

.btn-print:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-print .btn-icon {
    font-size: 18px;
}

.result-area {
    font-size: 13px;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.result-area:empty { display: none; }

.result-success {
    background: rgba(63, 185, 80, 0.12);
    border: 1px solid rgba(63, 185, 80, 0.3);
    color: var(--green);
    padding: 10px 14px;
    border-radius: 8px;
}

.result-error {
    background: rgba(248, 81, 73, 0.1);
    border: 1px solid rgba(248, 81, 73, 0.3);
    color: var(--red);
    padding: 10px 14px;
    border-radius: 8px;
}

.result-error pre {
    font-size: 11px;
    margin-top: 6px;
    white-space: pre-wrap;
    word-break: break-all;
    opacity: 0.85;
}

.result-error details summary {
    cursor: pointer;
    margin-top: 6px;
    color: var(--muted);
    font-size: 12px;
}

.result-error details pre {
    background: rgba(0,0,0,0.3);
    padding: 8px;
    border-radius: 4px;
    margin-top: 4px;
}

/* ── Preview Panel ───────────────────────────────────────────── */
.preview-panel {
    background: #181c24;
    display: flex;
    flex-direction: column;
    align-items: center;
    overflow-y: auto;
    padding: 32px 24px;
    gap: 16px;
}

.preview-panel::-webkit-scrollbar { width: 6px; }
.preview-panel::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

.preview-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--muted);
}

/* ── Receipt ─────────────────────────────────────────────────── */
.receipt-wrapper {
    width: 100%;
    max-width: 340px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.receipt {
    width: 100%;
    background: var(--receipt-bg);
    border-radius: 3px;
    box-shadow:
        0 2px 8px rgba(0,0,0,0.4),
        0 8px 32px rgba(0,0,0,0.3),
        inset 0 0 0 1px rgba(0,0,0,0.06);
    position: relative;
    color: var(--receipt-text);
}

.receipt-edge {
    height: 14px;
    background:
        linear-gradient(135deg, var(--receipt-bg) 33.33%, transparent 33.33%) 0 0 / 14px 14px,
        linear-gradient(-135deg, var(--receipt-bg) 33.33%, transparent 33.33%) 0 0 / 14px 14px;
    background-color: #181c24;
}

.receipt-edge-top {
    border-radius: 3px 3px 0 0;
}

.receipt-edge-bottom {
    transform: scaleY(-1);
    border-radius: 0 0 3px 3px;
}

.receipt-inner {
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.receipt-sep {
    border: none;
    border-top: 1.5px solid var(--receipt-sep);
    margin: 10px 0;
}

.receipt-sep-dashed {
    border: none;
    border-top: 1.5px dashed var(--receipt-sep);
    margin: 10px 0;
}

.r-header {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.5px;
    padding: 4px 0;
    word-break: break-word;
}

.r-title {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.3;
    padding: 4px 0;
    word-break: break-word;
}

.r-body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    padding: 4px 0;
}

.r-footer {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-family: 'Courier New', Courier, monospace;
    font-size: 10px;
    color: #666;
    padding: 2px 0;
    gap: 8px;
}

.r-trailer {
    font-style: italic;
    text-align: right;
}

.r-qr {
    display: flex;
    justify-content: center;
    padding: 8px 0 4px;
}

.qr-box {
    width: 72px;
    height: 72px;
    background: repeating-linear-gradient(
        45deg,
        #000 0, #000 2px,
        transparent 2px, transparent 6px
    );
    border: 2px solid #000;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.qr-box::before {
    content: 'QR';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.85);
    font-size: 11px;
    font-weight: 700;
    color: #000;
}

.r-cut {
    border-top: 2px dashed #aaa;
    margin: 0;
    padding: 5px 18px;
    background: var(--receipt-bg);
    font-family: monospace;
    font-size: 11px;
    color: #999;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Spinner ──────────────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }
.spinning { display: inline-block; animation: spin 0.7s linear infinite; }

/* ── Hint text ───────────────────────────────────────────────── */
.hint {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.4;
    font-style: italic;
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 860px) {
    .app-main {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
        height: auto;
        overflow: visible;
    }
    .preview-panel {
        min-height: 420px;
    }
    .control-panel {
        max-height: none;
        overflow: visible;
    }
}

@media (max-width: 560px) {
    .field-row, .field-2col { grid-template-columns: 1fr; }
    .toggle-row { grid-template-columns: 1fr; }
    .app-header { padding: 0 16px; }
    .form-body { padding: 14px; }
}
</style>
</head>
<body>

<header class="app-header">
  <div class="brand">
    <div class="brand-icon">🖨</div>
    <div>
      <span class="brand-name">WebTick</span>
      <span class="brand-sub">ESC/POS Ticket Printer</span>
    </div>
  </div>
  <div class="header-right">
    <div class="printer-pill">
      <div class="printer-dot" id="printer-dot"></div>
      <span id="active-printer-name">Loading...</span>
    </div>
  </div>
</header>

<main class="app-main">

  <!-- ── Control Panel ─────────────────────────────────────── -->
  <div class="control-panel">
    <div class="form-body">

      <!-- Content -->
      <div class="form-section">
        <div class="section-header open" data-target="s-content">
          <div class="section-title"><span class="section-icon">✏️</span> Content</div>
          <span class="section-chevron">▼</span>
        </div>
        <div class="section-body" id="s-content">
          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" placeholder="Ticket" autocomplete="off">
          </div>
          <div class="field">
            <label for="header">Header <span style="font-weight:400;text-transform:none">(super-header above title)</span></label>
            <input type="text" id="header" name="header" placeholder="Optional — e.g. ACME CORP" autocomplete="off">
          </div>
          <div class="field">
            <label for="body">Body</label>
            <textarea id="body" name="body" rows="5" placeholder="Body text (each line printed separately)…"></textarea>
          </div>
          <div class="field-2col">
            <div class="field">
              <label for="trailer">Trailer</label>
              <input type="text" id="trailer" name="trailer" placeholder="Footer note" autocomplete="off">
            </div>
            <div class="field">
              <label for="qrdata">QR Code Data</label>
              <input type="text" id="qrdata" name="qrdata" placeholder="URL or text" autocomplete="off">
            </div>
          </div>
        </div>
      </div>

      <!-- Typography -->
      <div class="form-section">
        <div class="section-header open" data-target="s-typo">
          <div class="section-title"><span class="section-icon">🔤</span> Typography</div>
          <span class="section-chevron">▼</span>
        </div>
        <div class="section-body" id="s-typo">
          <div class="field-row">
            <div class="field">
              <label>Header Size</label>
              <div class="size-control">
                <input type="range" id="hsize" name="hsize" min="1" max="60" value="4">
                <span class="size-val" id="hsize-val">4</span>
              </div>
            </div>
            <div class="field">
              <label>Title Size</label>
              <div class="size-control">
                <input type="range" id="tsize" name="tsize" min="1" max="60" value="2">
                <span class="size-val" id="tsize-val">2</span>
              </div>
            </div>
            <div class="field">
              <label>Body Size</label>
              <div class="size-control">
                <input type="range" id="size" name="size" min="1" max="60" value="1">
                <span class="size-val" id="size-val">1</span>
              </div>
            </div>
          </div>
          <div class="field">
            <label class="toggle-label">
              <input type="checkbox" id="center" name="center">
              <span class="toggle-switch"></span>
              <span class="toggle-text">Center-align title</span>
            </label>
          </div>
          <div class="field">
            <label for="headerttf">Header Font (TTF)</label>
            <select id="headerttf" name="headerttf">
              <?php foreach ($font_options as $k => $v): ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="titlettf">Title Font (TTF)</label>
            <select id="titlettf" name="titlettf">
              <?php foreach ($font_options as $k => $v): ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="bodyttf">Body Font (TTF)</label>
            <select id="bodyttf" name="bodyttf">
              <?php foreach ($font_options as $k => $v): ?>
              <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">TTF renders text as a raster image — ideal for custom looks.</span>
          </div>
          <div class="field">
            <label class="toggle-label">
              <input type="checkbox" id="new_text_render" name="new_text_render">
              <span class="toggle-switch"></span>
              <span class="toggle-text">Use new TTF renderer (Pillow textbbox)</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Hardware -->
      <div class="form-section">
        <div class="section-header open" data-target="s-hw">
          <div class="section-title"><span class="section-icon">⚙️</span> Hardware</div>
          <span class="section-chevron">▼</span>
        </div>
        <div class="section-body" id="s-hw">
          <div class="field">
            <label for="dest">Printer Destination</label>
            <select id="dest" name="dest">
              <?php foreach ($printers as $p):
                $sel = (strpos($p, 'CITIZEN') !== false || strpos($p, 'citizen') !== false) ? ' selected' : '';
              ?>
              <option value="<?= htmlspecialchars($p) ?>"<?= $sel ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field-2col">
            <div class="field">
              <label for="pixwidth">Pixel Width</label>
              <select id="pixwidth" name="pixwidth">
                <option value="576" selected>576 px (CT-S310 / 80 mm)</option>
                <option value="384">384 px (58 mm generic)</option>
                <option value="832">832 px (4-inch)</option>
              </select>
            </div>
            <div class="field">
              <label for="impl">Graphics Impl</label>
              <select id="impl" name="impl">
                <option value="bitImageRaster" selected>bitImageRaster</option>
                <option value="graphics">graphics</option>
                <option value="bitImageColumn">bitImageColumn</option>
              </select>
            </div>
          </div>
          <div class="field">
            <label class="toggle-label">
              <input type="checkbox" id="landscape" name="landscape">
              <span class="toggle-switch"></span>
              <span class="toggle-text">Landscape / banner mode (alpha)</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Finishing -->
      <div class="form-section">
        <div class="section-header open" data-target="s-finish">
          <div class="section-title"><span class="section-icon">✂️</span> Finishing</div>
          <span class="section-chevron">▼</span>
        </div>
        <div class="section-body" id="s-finish">
          <div class="toggle-row">
            <div class="field">
              <label class="toggle-label">
                <input type="checkbox" id="cut" name="cut" checked>
                <span class="toggle-switch"></span>
                <span class="toggle-text">Cut paper</span>
              </label>
            </div>
            <div class="field">
              <label class="toggle-label">
                <input type="checkbox" id="beep" name="beep">
                <span class="toggle-switch"></span>
                <span class="toggle-text">Beep</span>
              </label>
            </div>
          </div>
          <div class="field">
            <label for="eject">Eject Lines (paper advance after cut)</label>
            <div class="size-control">
              <input type="range" id="eject" name="eject" min="0" max="20" value="3">
              <span class="size-val" id="eject-val">3</span>
            </div>
          </div>
          <div class="field">
            <label class="toggle-label" style="color: var(--amber) !important">
              <input type="checkbox" id="dummy" name="dummy">
              <span class="toggle-switch"></span>
              <span class="toggle-text" style="color: var(--amber)">Dummy mode — process but don't send to printer</span>
            </label>
          </div>
        </div>
      </div>

    </div><!-- /form-body -->

    <div class="actions-bar">
      <button type="button" class="btn-print" id="print-btn">
        <span class="btn-icon" id="print-icon">🖨</span>
        <span id="print-label">Print Ticket</span>
      </button>
      <div class="result-area" id="result-area"></div>
    </div>
  </div><!-- /control-panel -->

  <!-- ── Preview Panel ──────────────────────────────────────── -->
  <div class="preview-panel">
    <div class="preview-label">Live Preview</div>

    <div class="receipt-wrapper">
      <div class="receipt" id="receipt">
        <div class="receipt-edge receipt-edge-top"></div>
        <div class="receipt-inner" id="receipt-inner">

          <!-- Header block (hidden when empty) -->
          <div id="prev-header-block" style="display:none">
            <div id="prev-header" class="r-header" style="text-align:left"></div>
            <hr class="receipt-sep">
          </div>

          <!-- Title block -->
          <div id="prev-title" class="r-title">Ticket</div>
          <hr class="receipt-sep">

          <!-- Body block -->
          <div id="prev-body" class="r-body"></div>

          <!-- QR block (hidden when empty) -->
          <div id="prev-qr-block" style="display:none">
            <hr class="receipt-sep-dashed">
            <div class="r-qr"><div class="qr-box" id="prev-qr-box" title=""></div></div>
          </div>

          <!-- Footer -->
          <hr class="receipt-sep">
          <div class="r-footer">
            <span id="prev-ts"></span>
            <span id="prev-trailer-text" class="r-trailer"></span>
          </div>

        </div><!-- /receipt-inner -->

        <!-- Cut indicator (hidden when off) -->
        <div id="prev-cut" class="r-cut" style="display:none">
          ✂ &nbsp;─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ ─
        </div>

        <div class="receipt-edge receipt-edge-bottom"></div>
      </div>
    </div>
  </div>

</main>

<script>
// ── Section collapse ────────────────────────────────────────────
document.querySelectorAll('.section-header').forEach(hdr => {
    hdr.addEventListener('click', () => {
        const target = document.getElementById(hdr.dataset.target);
        if (!target) return;
        const open = hdr.classList.toggle('open');
        target.style.display = open ? '' : 'none';
    });
});

// ── Slider value display ─────────────────────────────────────────
['hsize', 'tsize', 'size', 'eject'].forEach(id => {
    const el = document.getElementById(id);
    const val = document.getElementById(id + '-val');
    if (el && val) {
        el.addEventListener('input', () => { val.textContent = el.value; });
    }
});

// ── Preview update ───────────────────────────────────────────────
const FONT_CSS = {
    '':                     "'Courier New', Courier, monospace",
    'liberation-sans':      'sans-serif',
    'liberation-sans-bold': 'sans-serif',
    'liberation-mono':      "'Courier New', Courier, monospace",
    'liberation-mono-bold': "'Courier New', Courier, monospace",
    'liberation-serif':     'Georgia, serif',
    'liberation-serif-bold':'Georgia, serif',
    'ubuntu':               'sans-serif',
    'ubuntu-bold':          'sans-serif',
    'ubuntu-mono':          "'Courier New', Courier, monospace",
};

function fmtTs() {
    return new Date().toLocaleString('en-US', {
        month:'2-digit', day:'2-digit', year:'numeric',
        hour:'2-digit', minute:'2-digit', second:'2-digit'
    });
}

function updatePreview() {
    const title    = document.getElementById('title').value || 'Ticket';
    const header   = document.getElementById('header').value.trim();
    const body     = document.getElementById('body').value;
    const trailer  = document.getElementById('trailer').value.trim();
    const qrdata   = document.getElementById('qrdata').value.trim();
    const center   = document.getElementById('center').checked;
    const cut      = document.getElementById('cut').checked;
    const hsize    = parseInt(document.getElementById('hsize').value) || 4;
    const tsize    = parseInt(document.getElementById('tsize').value) || 2;
    const size     = parseInt(document.getElementById('size').value) || 1;
    const bodyttf  = document.getElementById('bodyttf').value;
    const headerttf= document.getElementById('headerttf').value;
    const titlettf = document.getElementById('titlettf').value;

    const align = center ? 'center' : 'left';

    // Header
    const headerBlock = document.getElementById('prev-header-block');
    const prevHeader  = document.getElementById('prev-header');
    if (header) {
        headerBlock.style.display = '';
        prevHeader.textContent = header;
        prevHeader.style.fontSize = (hsize * 5 + 10) + 'px';
        prevHeader.style.textAlign = align;
        prevHeader.style.fontFamily = FONT_CSS[headerttf] || 'sans-serif';
        prevHeader.style.fontWeight = headerttf.includes('bold') ? '800' : '800';
    } else {
        headerBlock.style.display = 'none';
    }

    // Title
    const prevTitle = document.getElementById('prev-title');
    prevTitle.textContent = title;
    prevTitle.style.fontSize = (tsize * 4 + 10) + 'px';
    prevTitle.style.textAlign = align;
    prevTitle.style.fontFamily = FONT_CSS[titlettf] || 'sans-serif';
    prevTitle.style.fontWeight = titlettf.includes('bold') ? '800' : '700';

    // Body
    const prevBody = document.getElementById('prev-body');
    prevBody.textContent = body;
    prevBody.style.fontSize = (size * 2 + 10) + 'px';
    prevBody.style.textAlign = align;
    prevBody.style.fontFamily = FONT_CSS[bodyttf] || "'Courier New', Courier, monospace";
    prevBody.style.display = body ? '' : 'none';

    // QR
    const qrBlock = document.getElementById('prev-qr-block');
    const qrBox   = document.getElementById('prev-qr-box');
    qrBlock.style.display = qrdata ? '' : 'none';
    if (qrdata) qrBox.title = qrdata;

    // Footer
    document.getElementById('prev-trailer-text').textContent = trailer;

    // Cut
    document.getElementById('prev-cut').style.display = cut ? '' : 'none';
}

// Live timestamp
document.getElementById('prev-ts').textContent = fmtTs();
setInterval(() => { document.getElementById('prev-ts').textContent = fmtTs(); }, 1000);

// Attach all form listeners
document.querySelectorAll('input, textarea, select').forEach(el => {
    el.addEventListener('input', updatePreview);
    el.addEventListener('change', updatePreview);
});

// Header-linked printer pill
document.getElementById('dest').addEventListener('change', function() {
    document.getElementById('active-printer-name').textContent = this.value;
});

// Set initial printer pill
window.addEventListener('DOMContentLoaded', () => {
    const dest = document.getElementById('dest');
    if (dest) document.getElementById('active-printer-name').textContent = dest.value;
    updatePreview();
});

// ── Print / AJAX submit ──────────────────────────────────────────
document.getElementById('print-btn').addEventListener('click', async function () {
    const form = document.createElement('form');
    const fields = [
        'title','header','body','trailer','qrdata',
        'hsize','tsize','size','eject','pixwidth','impl',
        'dest','headerttf','titlettf','bodyttf'
    ];
    const toggles = ['center','cut','beep','landscape','dummy','new_text_render'];
    const data = {};
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) data[id] = el.value;
    });
    toggles.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.checked) data[id] = '1';
    });

    const btn = this;
    const icon = document.getElementById('print-icon');
    const label = document.getElementById('print-label');
    btn.disabled = true;
    icon.textContent = '⏳';
    icon.className = 'btn-icon spinning';
    label.textContent = 'Sending to printer…';

    try {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));

        const res = await fetch('print.php', { method: 'POST', body: fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        showResult(json);
    } catch (e) {
        showResult({ success: false, error: String(e) });
    } finally {
        btn.disabled = false;
        icon.className = 'btn-icon';
        icon.textContent = '🖨';
        label.textContent = 'Print Ticket';
    }
});

function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;');
}

function showResult(r) {
    const area = document.getElementById('result-area');
    if (r.success) {
        area.className = 'result-area result-success';
        area.innerHTML = '✓ Ticket sent successfully!';
    } else {
        area.className = 'result-area result-error';
        const msg = (r.error || r.stderr || 'Process exited with code ' + r.exit_code).trim();
        area.innerHTML = `<strong>Print failed</strong><br><pre>${esc(msg)}</pre>`;
        if (r.command) {
            area.innerHTML += `<details><summary>Command (dummy mode)</summary><pre>${esc(r.command)}</pre></details>`;
        }
    }
    clearTimeout(area._timer);
    area._timer = setTimeout(() => {
        area.className = 'result-area';
        area.innerHTML = '';
    }, 10000);
}
</script>
</body>
</html>
