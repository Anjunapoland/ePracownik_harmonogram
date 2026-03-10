<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$year  = (int)($_POST['year'] ?? 0);
$month = (int)($_POST['month'] ?? 0);

if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) {
    json_out(['error'=>'Nieprawidłowy miesiąc'], 400);
}

$from = sprintf('%04d-%02d-01', $year, $month);
$dim  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$to   = sprintf('%04d-%02d-%02d', $year, $month, $dim);

$db = get_db();
// Remove dyzur notifications for this month
$db->prepare("DELETE FROM notifications WHERE type='dyzur' AND related_date BETWEEN ? AND ?")->execute([$from, $to]);
// Remove schedule entries
$stmt = $db->prepare('DELETE FROM schedule_entries WHERE entry_date BETWEEN ? AND ?');
$stmt->execute([$from, $to]);
$count = $stmt->rowCount();

json_out(['ok'=>true, 'count'=>$count]);
