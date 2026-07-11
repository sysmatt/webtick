<?php
require_once __DIR__ . '/lib/config.php';
$config = webtick_load_config();

if ($config['auth']['enabled']) {
    require $config['auth']['simplewebauth_dir'] . '/auth.php';
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

define('TOOL_PYTHON', $config['tool']['python_bin']);
define('TOOL_SCRIPT', $config['tool']['script_path']);

// Whitelisted TTF font paths — no arbitrary user-supplied paths
$FONT_MAP = $config['fonts'] + [''=> ''];

$ALLOWED_IMPLS = $config['printer']['impls'];

// ── Sanitize inputs ──────────────────────────────────────────────
$title   = trim($_POST['title'] ?? '') ?: 'Ticket';
$header  = trim($_POST['header'] ?? '');
$body    = $_POST['body'] ?? '';
$dest    = trim($_POST['dest'] ?? $config['printer']['default_queue']);
$trailer = trim($_POST['trailer'] ?? '');
$qrdata  = trim($_POST['qrdata'] ?? '');
$qrsize  = max(1, min(16, (int)($_POST['qrsize'] ?? 6)));

$fs = $config['font_sizes'];
$size    = max($fs['body']['min'],   min($fs['body']['max'],   (int)($_POST['size']  ?? $fs['body']['default'])));
$tsize   = max($fs['title']['min'],  min($fs['title']['max'],  (int)($_POST['tsize'] ?? $fs['title']['default'])));
$hsize   = max($fs['header']['min'], min($fs['header']['max'], (int)($_POST['hsize'] ?? $fs['header']['default'])));
$eject   = max(0, min(20, (int)($_POST['eject']  ?? 3)));

$px_allowed = $config['printer']['widths'];
$pixwidth = in_array((int)($_POST['pixwidth'] ?? $config['printer']['default_width']), $px_allowed, true)
    ? (int)$_POST['pixwidth'] : $config['printer']['default_width'];

$impl = in_array($_POST['impl'] ?? '', $ALLOWED_IMPLS, true)
    ? $_POST['impl'] : $config['printer']['default_impl'];

$cut            = !empty($_POST['cut']);
$beep           = !empty($_POST['beep']);
$center         = !empty($_POST['center']);
$landscape      = !empty($_POST['landscape']);
$new_text_render= !empty($_POST['new_text_render']);
$imagesep       = !empty($_POST['imagesep']);
$imagename      = !empty($_POST['imagename']);

$bodyttf   = $FONT_MAP[$_POST['bodyttf']   ?? ''] ?? '';
$headerttf = $FONT_MAP[$_POST['headerttf'] ?? ''] ?? '';
$titlettf  = $FONT_MAP[$_POST['titlettf']  ?? ''] ?? '';

$logosize = max(16, min(max($px_allowed), (int)($_POST['logosize'] ?? 300)));

// Basic sanity check on printer destination (alphanumeric, _, -, .)
if (!preg_match('/^[a-zA-Z0-9_\-.]+$/', $dest)) {
    echo json_encode(['success' => false, 'error' => 'Invalid printer destination name']);
    exit;
}

// ── Handle uploaded files ────────────────────────────────────────
$tmp_files = [];

function save_upload(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return '';
    if (!getimagesize($file['tmp_name'])) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['png','jpg','jpeg','gif','bmp','webp'];
    if (!in_array($ext, $allowed_ext)) return '';
    $path = sys_get_temp_dir() . '/webtick_' . uniqid() . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $path) ? $path : '';
}

$logo_path = '';
if (!empty($_FILES['logo']['tmp_name'])) {
    $logo_path = save_upload($_FILES['logo']);
    if ($logo_path) $tmp_files[] = $logo_path;
}

$image_paths = [];
if (!empty($_FILES['images']['tmp_name'])) {
    foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
        $entry = ['name' => $_FILES['images']['name'][$i],
                  'tmp_name' => $tmp_name,
                  'error' => $_FILES['images']['error'][$i]];
        $p = save_upload($entry);
        if ($p) { $image_paths[] = $p; $tmp_files[] = $p; }
    }
}

// ── Build command ────────────────────────────────────────────────
$cmd  = escapeshellarg(TOOL_PYTHON) . ' ' . escapeshellarg(TOOL_SCRIPT);
$cmd .= ' -d ' . escapeshellarg($dest);
if ($bodyttf   !== '') $cmd .= ' -S '      . $size;
if ($titlettf  !== '') $cmd .= ' -T '      . $tsize;
if ($headerttf !== '') $cmd .= ' --hsize ' . $hsize;
$cmd .= ' -e ' . $eject;
$cmd .= ' -P ' . $pixwidth;
$cmd .= ' --impl ' . escapeshellarg($impl);

if ($header  !== '') $cmd .= ' --header '  . escapeshellarg($header);
if ($trailer !== '') $cmd .= ' --trailer ' . escapeshellarg($trailer);
if ($qrdata  !== '') $cmd .= ' --qrdata '  . escapeshellarg($qrdata) . ' --qrsize ' . $qrsize;

if ($logo_path !== '') $cmd .= ' --logo ' . escapeshellarg($logo_path) . ' --logomax ' . $logosize;
foreach ($image_paths as $p) $cmd .= ' --image ' . escapeshellarg($p);
if ($imagesep)  $cmd .= ' --imagesep';
if ($imagename) $cmd .= ' --imagename';

if ($bodyttf   !== '') $cmd .= ' --bodyttf '   . escapeshellarg($bodyttf);
if ($headerttf !== '') $cmd .= ' --headerttf ' . escapeshellarg($headerttf);
if ($titlettf  !== '') $cmd .= ' --titlettf '  . escapeshellarg($titlettf);

if ($cut)             $cmd .= ' --cut';
if ($beep)            $cmd .= ' --beep';
if ($center)          $cmd .= ' -C';
if ($landscape)       $cmd .= ' --landscape';
if ($new_text_render) $cmd .= ' --new-text-render';

$cmd .= ' ' . escapeshellarg($title);

// ── Execute ──────────────────────────────────────────────────────
$descriptorspec = [
    0 => ['pipe', 'r'],  // stdin  → body text
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
];

$process = proc_open($cmd, $descriptorspec, $pipes);

if (!is_resource($process)) {
    echo json_encode(['success' => false, 'error' => 'Failed to start print process']);
    exit;
}

fwrite($pipes[0], $body);
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exit_code = proc_close($process);

foreach ($tmp_files as $f) @unlink($f);

// ── Respond ──────────────────────────────────────────────────────
$resp = [
    'success'   => $exit_code === 0,
    'exit_code' => $exit_code,
    'stdout'    => $stdout,
    'stderr'    => $stderr,
];

if ($exit_code !== 0) {
    $resp['error'] = trim($stderr ?: $stdout ?: 'Process exited with code ' . $exit_code);
}

echo json_encode($resp);
