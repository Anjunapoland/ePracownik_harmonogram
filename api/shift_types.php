<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $code  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['code'] ?? '')));
    $label = trim($_POST['label'] ?? '');
    $color = trim($_POST['color'] ?? '#ffffff');
    $textColor = trim($_POST['text_color'] ?? '#000000');
    $start = trim($_POST['default_start'] ?? '');
    $end   = trim($_POST['default_end'] ?? '');
    $order = (int)($_POST['sort_order'] ?? 0);

    if (!$code || !$label) json_out(['error'=>'Kod i nazwa są wymagane'], 400);
    if (strlen($code) > 50) json_out(['error'=>'Kod za długi (max 50 znaków)'], 400);
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#ffffff';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textColor)) $textColor = '#000000';
    if ($start && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = '';
    if ($end && !preg_match('/^\d{2}:\d{2}$/', $end)) $end = '';

    save_shift_type($code, $label, $color, $textColor, $start, $end, $order);
    json_out(['ok'=>true]);

} elseif ($action === 'delete') {
    $code = trim($_POST['code'] ?? '');
    if (!$code) json_out(['error'=>'Brak kodu'], 400);

    // Check if shift type is in use
    $db = get_db();
    $stmt = $db->prepare('SELECT COUNT(*) FROM schedule_entries WHERE shift_type=?');
    $stmt->execute([$code]);
    $inUse = (int)$stmt->fetchColumn();

    if ($inUse > 0) {
        json_out(['error'=>"Nie można usunąć — typ jest używany w $inUse wpisach harmonogramu. Najpierw zmień te wpisy."], 400);
    }

    if (!delete_shift_type($code)) {
        json_out(['error'=>'Tego typu nie można usunąć (chroniony)'], 400);
    }
    json_out(['ok'=>true]);

} elseif ($action === 'list') {
    json_out(['ok'=>true, 'types'=>get_shift_types()]);

} else {
    json_out(['error'=>'Nieznana akcja'], 400);
}
