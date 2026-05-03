<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

#define('TOOL_PYTHON', '/home/sysmatt/Documents/workspace/sysmatt.escpos.ticket.print/.venv/bin/python');
define('TOOL_PYTHON', '/usr/bin/python3');
#define('TOOL_SCRIPT', '/home/sysmatt/Documents/workspace/sysmatt.escpos.ticket.print/sysmatt.escpos.ticket.print');
define('TOOL_SCRIPT', '/opt/sage/local/platform/scripts/sysmatt.escpos.ticket.print');

// Whitelisted TTF font paths — no arbitrary user-supplied paths
const FONT_MAP = [
    ''                     => '',
    'liberation-sans'      => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    'liberation-sans-bold' => '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    'liberation-sans-italic'=> '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf',
    'liberation-mono'      => '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
    'liberation-mono-bold' => '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
    'liberation-serif'     => '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
    'liberation-serif-bold'=> '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
    'ubuntu'               => '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
    'ubuntu-bold'          => '/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf',
    'ubuntu-mono'          => '/usr/share/fonts/truetype/ubuntu/UbuntuMono-R.ttf',
];

const ALLOWED_IMPLS = ['bitImageRaster', 'graphics', 'bitImageColumn'];

// ── Sanitize inputs ──────────────────────────────────────────────
$title   = trim($_POST['title'] ?? '') ?: 'Ticket';
$header  = trim($_POST['header'] ?? '');
$body    = $_POST['body'] ?? '';
$dest    = trim($_POST['dest'] ?? 'CITIZEN_CT_S310_clocky4');
$trailer = trim($_POST['trailer'] ?? '');
$qrdata  = trim($_POST['qrdata'] ?? '');

$size    = max(1, min(60, (int)($_POST['size']    ?? 1)));
$tsize   = max(1, min(60, (int)($_POST['tsize']   ?? 2)));
$hsize   = max(1, min(60, (int)($_POST['hsize']   ?? 4)));
$eject   = max(0, min(20, (int)($_POST['eject']  ?? 3)));

$px_allowed = [384, 576, 832];
$pixwidth = in_array((int)($_POST['pixwidth'] ?? 576), $px_allowed)
    ? (int)$_POST['pixwidth'] : 576;

$impl = in_array($_POST['impl'] ?? '', ALLOWED_IMPLS)
    ? $_POST['impl'] : 'bitImageRaster';

$cut            = !empty($_POST['cut']);
$beep           = !empty($_POST['beep']);
$center         = !empty($_POST['center']);
$landscape      = !empty($_POST['landscape']);
$dummy          = !empty($_POST['dummy']);
$new_text_render= !empty($_POST['new_text_render']);

$bodyttf   = FONT_MAP[$_POST['bodyttf']   ?? ''] ?? '';
$headerttf = FONT_MAP[$_POST['headerttf'] ?? ''] ?? '';
$titlettf  = FONT_MAP[$_POST['titlettf']  ?? ''] ?? '';

// Basic sanity check on printer destination (alphanumeric, _, -, .)
if (!preg_match('/^[a-zA-Z0-9_\-.]+$/', $dest)) {
    echo json_encode(['success' => false, 'error' => 'Invalid printer destination name']);
    exit;
}

// ── Build command ────────────────────────────────────────────────
$cmd  = escapeshellarg(TOOL_PYTHON) . ' ' . escapeshellarg(TOOL_SCRIPT);
$cmd .= ' -d ' . escapeshellarg($dest);
$cmd .= ' -S ' . $size;
$cmd .= ' -T ' . $tsize;
$cmd .= ' --hsize ' . $hsize;
$cmd .= ' -e ' . $eject;
$cmd .= ' -P ' . $pixwidth;
$cmd .= ' --impl ' . escapeshellarg($impl);

if ($header  !== '') $cmd .= ' --header '  . escapeshellarg($header);
if ($trailer !== '') $cmd .= ' --trailer ' . escapeshellarg($trailer);
if ($qrdata  !== '') $cmd .= ' --qrdata '  . escapeshellarg($qrdata);

if ($bodyttf   !== '') $cmd .= ' --bodyttf '   . escapeshellarg($bodyttf);
if ($headerttf !== '') $cmd .= ' --headerttf ' . escapeshellarg($headerttf);
if ($titlettf  !== '') $cmd .= ' --titlettf '  . escapeshellarg($titlettf);

if ($cut)             $cmd .= ' --cut';
if ($beep)            $cmd .= ' --beep';
if ($center)          $cmd .= ' -C';
if ($landscape)       $cmd .= ' --landscape';
if ($dummy)           $cmd .= ' --dummy';
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

// ── Respond ──────────────────────────────────────────────────────
$resp = [
    'success'   => $exit_code === 0,
    'exit_code' => $exit_code,
];

if ($exit_code !== 0) {
    $resp['error'] = trim($stderr ?: $stdout ?: 'Process exited with code ' . $exit_code);
}

if ($dummy) {
    $resp['command'] = $cmd;   // expose command only in dummy/test mode
    $resp['output']  = $stdout;
    $resp['stderr']  = $stderr;
}

echo json_encode($resp);
