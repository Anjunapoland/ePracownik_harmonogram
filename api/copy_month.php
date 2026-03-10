<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$srcYear  = (int)($_POST['src_year']??0);
$srcMonth = (int)($_POST['src_month']??0);
$dstYear  = (int)($_POST['dst_year']??0);
$dstMonth = (int)($_POST['dst_month']??0);
$scope    = $_POST['scope']??'all'; // 'all' or specific user_id
$overwrite = ($_POST['overwrite']??'0') === '1';

if (!$srcYear || !$srcMonth || !$dstYear || !$dstMonth) json_out(['error'=>'Brak danych'], 400);
if ($srcYear==$dstYear && $srcMonth==$dstMonth) json_out(['error'=>'Miesiąc źródłowy i docelowy są takie same'], 400);

$db = get_db();
$dstDim = cal_days_in_month(CAL_GREGORIAN, $dstMonth, $dstYear);

// Get source entries
$sql = 'SELECT * FROM schedule_entries WHERE YEAR(entry_date)=? AND MONTH(entry_date)=?';
$params = [$srcYear, $srcMonth];
if ($scope !== 'all') {
    $sql .= ' AND user_id=?';
    $params[] = (int)$scope;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$srcEntries = $stmt->fetchAll();

$count = 0;
foreach ($srcEntries as $entry) {
    $srcDay = (int)date('j', strtotime($entry['entry_date']));

    // Skip if day doesn't exist in destination month
    if ($srcDay > $dstDim) continue;

    $dstDate = sprintf('%04d-%02d-%02d', $dstYear, $dstMonth, $srcDay);

    // Check if entry exists in destination
    $stmt2 = $db->prepare('SELECT id FROM schedule_entries WHERE user_id=? AND entry_date=?');
    $stmt2->execute([$entry['user_id'], $dstDate]);
    $existing = $stmt2->fetchColumn();

    if ($existing && !$overwrite) continue;

    $prevType = null;
    if ($existing) {
        $prevStmt = $db->prepare('SELECT shift_type FROM schedule_entries WHERE id=?');
        $prevStmt->execute([$existing]);
        $prevType = $prevStmt->fetchColumn();

        $db->prepare('UPDATE schedule_entries SET shift_type=?, shift_start=?, shift_end=?, note=?, updated_at=NOW() WHERE id=?')
           ->execute([$entry['shift_type'], $entry['shift_start'], $entry['shift_end'], $entry['note'], $existing]);
    } else {
        $db->prepare('INSERT INTO schedule_entries (user_id, entry_date, shift_type, shift_start, shift_end, note, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())')
           ->execute([$entry['user_id'], $dstDate, $entry['shift_type'], $entry['shift_start'], $entry['shift_end'], $entry['note']]);
    }
    apply_wolne_overtime_balance_change((int)$entry['user_id'], $dstDate, $prevType !== false ? (string)$prevType : null, (string)$entry['shift_type']);
    $count++;
}

json_out(['ok'=>true, 'count'=>$count]);
