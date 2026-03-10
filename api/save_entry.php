<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$uid  = (int)($_POST['user_id']??0);
$date = $_POST['entry_date']??'';
$type = $_POST['shift_type']??'standard';
$start= $_POST['shift_start']??null;
$end  = $_POST['shift_end']??null;
$note = trim($_POST['note']??'');

if (!$uid || !$date) json_out(['error'=>'Brak danych'], 400);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_out(['error'=>'Zły format daty'], 400);
if (!isset(get_shift_types()[$type])) $type = 'standard';
// No-time types: force clear hours
$noTimeCodes = ['urlop','urlop_na_zadanie','chorobowe','wolne','brak'];
$stLabel = mb_strtolower(get_shift_types()[$type]['label'] ?? '');
$isNoTime = in_array($type, $noTimeCodes) || strpos($stLabel,'urlop')!==false || strpos($stLabel,'chorobow')!==false;
if ($isNoTime) { $start = null; $end = null; }
if ($start && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = null;
if ($end   && !preg_match('/^\d{2}:\d{2}$/', $end))   $end   = null;

$db = get_db();

// Upsert: check if entry exists for this user+date
$stmt = $db->prepare('SELECT id, shift_type, shift_start, shift_end, note FROM schedule_entries WHERE user_id=? AND entry_date=?');
$stmt->execute([$uid, $date]);
$existingRow = $stmt->fetch();
$existing = $existingRow ? $existingRow['id'] : null;
$prevType = $existingRow ? $existingRow['shift_type'] : null;
$prevStart = $existingRow['shift_start'] ?? null;
$prevEnd = $existingRow['shift_end'] ?? null;
$prevNote = $existingRow['note'] ?? '';

if ($existing) {
    $db->prepare('UPDATE schedule_entries SET shift_type=?, shift_start=?, shift_end=?, note=?, updated_at=NOW() WHERE id=?')
       ->execute([$type, $start, $end, $note, $existing]);
} else {
    $db->prepare('INSERT INTO schedule_entries (user_id, entry_date, shift_type, shift_start, shift_end, note, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())')
       ->execute([$uid, $date, $type, $start, $end, $note]);
}

apply_wolne_overtime_balance_change($uid, $date, $prevType, $type);

$changed = (
    !$existingRow ||
    (string)$prevType !== (string)$type ||
    (string)$prevStart !== (string)$start ||
    (string)$prevEnd !== (string)$end ||
    trim((string)$prevNote) !== trim((string)$note)
);

if ($changed) {
    create_or_replace_schedule_notification($uid, $type, $date, $start, $end, $note);
}

$restWarnings = check_single_rest($uid, $date, $start, $end);
json_out(['ok'=>true, 'rest_warnings'=>$restWarnings]);
