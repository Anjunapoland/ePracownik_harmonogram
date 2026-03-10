<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$id = (int)($_POST['id']??0);
if (!$id) json_out(['error'=>'Brak ID'], 400);

$db = get_db();
// Check if it's a dyzur before deleting — remove related notification
$stmt = $db->prepare('SELECT user_id, entry_date, shift_type FROM schedule_entries WHERE id=?');
$stmt->execute([$id]);
$entry = $stmt->fetch();

$db->prepare('DELETE FROM schedule_entries WHERE id=?')->execute([$id]);

if ($entry) {
    apply_wolne_overtime_balance_change((int)$entry['user_id'], (string)$entry['entry_date'], $entry['shift_type'] ?? null, null);
}

if ($entry && $entry['shift_type'] === 'dyzur') {
    $db->prepare("DELETE FROM notifications WHERE user_id=? AND type='dyzur' AND related_date=?")->execute([$entry['user_id'], $entry['entry_date']]);
}

json_out(['ok'=>true]);
