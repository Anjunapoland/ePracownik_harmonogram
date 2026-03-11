<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnien'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$year  = (int)($_POST['year'] ?? 0);
$month = (int)($_POST['month'] ?? 0);
$mode  = $_POST['mode'] ?? 'empty'; // 'empty' or 'overwrite'
$depts = $_POST['depts'] ?? [];

if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) {
    json_out(['error'=>'Nieprawidlowy miesiac'], 400);
}
if (empty($depts) || !is_array($depts)) {
    json_out(['error'=>'Wybierz przynajmniej jeden dzial'], 400);
}

$db = get_db();
$dim = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get employees from selected departments
$placeholders = implode(',', array_fill(0, count($depts), '?'));
$stmt = $db->prepare("SELECT id FROM users WHERE active=1 AND department IN ($placeholders)");
$stmt->execute($depts);
$empIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($empIds)) {
    json_out(['error'=>'Brak pracownikow w wybranych dzialach'], 400);
}

// Standard hours:
// Mon-Thu (1-4): 7:30 - 16:00
// Fri (5): 7:30 - 13:30
// Sat-Sun (6,0): skip

$count = 0;

// Prepare check statement
$checkStmt = $db->prepare('SELECT id FROM schedule_entries WHERE user_id=? AND entry_date=?');
$insertStmt = $db->prepare('INSERT INTO schedule_entries (user_id, entry_date, shift_type, shift_start, shift_end, note, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
$updateStmt = $db->prepare('UPDATE schedule_entries SET shift_type=?, shift_start=?, shift_end=?, note=?, updated_at=NOW() WHERE id=?');

foreach ($empIds as $uid) {
    for ($d = 1; $d <= $dim; $d++) {
        $dow = (int)date('w', mktime(0, 0, 0, $month, $d, $year));
        
        // Skip weekends
        if ($dow === 0 || $dow === 6) continue;
        
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        
        // Set hours based on day of week
        if ($dow >= 1 && $dow <= 4) {
            // Mon-Thu
            $start = '07:30';
            $end   = '16:00';
        } else {
            // Friday
            $start = '07:30';
            $end   = '13:30';
        }
        
        // Check if entry exists
        $checkStmt->execute([$uid, $date]);
        $existingId = $checkStmt->fetchColumn();
        
        if ($existingId) {
            if ($mode === 'overwrite') {
                $existingTypeStmt = $db->prepare('SELECT shift_type FROM schedule_entries WHERE id=?');
                $existingTypeStmt->execute([$existingId]);
                $prevType = $existingTypeStmt->fetchColumn();

                $updateStmt->execute(['standard', $start, $end, null, $existingId]);
                apply_wolne_overtime_balance_change((int)$uid, $date, $prevType !== false ? (string)$prevType : null, 'standard');
                $count++;
            }
            // mode=empty: skip existing entries
        } else {
            $insertStmt->execute([$uid, $date, 'standard', $start, $end, null]);
            $count++;
        }
    }
}

json_out(['ok'=>true, 'count'=>$count]);
