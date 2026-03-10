<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u || $u['role']!=='admin') json_out(['error'=>'Brak uprawnień'], 403);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$action = $_POST['action'] ?? 'month';
$type  = $_POST['shift_type'] ?? 'standard';
$start = $_POST['shift_start'] ?? null;
$end   = $_POST['shift_end'] ?? null;
$note  = trim($_POST['note'] ?? '');

if (!isset(get_shift_types()[$type])) $type = 'standard';
$noTimeTypes = ['urlop','urlop_na_zadanie','chorobowe','wolne','brak'];
if (in_array($type, $noTimeTypes, true)) { $start = null; $end = null; }
if ($start && !preg_match('/^\d{2}:\d{2}$/', $start)) $start = null;
if ($end   && !preg_match('/^\d{2}:\d{2}$/', $end))   $end   = null;

$db = get_db();

if ($action === 'selected_cells') {
    $cellsRaw = $_POST['cells'] ?? '[]';
    $cells = json_decode($cellsRaw, true);
    if (!is_array($cells)) json_out(['error'=>'Nieprawidłowa lista komórek'], 400);
    if (count($cells) < 2) json_out(['error'=>'Zaznacz minimum 2 komórki'], 400);
    if (count($cells) > 500) json_out(['error'=>'Za dużo komórek na raz (max 500)'], 400);

    $updated = [];
    $count = 0;
    $db->beginTransaction();
    try {
        foreach ($cells as $cell) {
            $uid = (int)($cell['employee_id'] ?? $cell['user_id'] ?? 0);
            $date = trim((string)($cell['date'] ?? ''));
            if (!$uid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Nieprawidłowe dane komórki');
            }

            $stmt = $db->prepare('SELECT id FROM schedule_entries WHERE user_id=? AND entry_date=?');
            $stmt->execute([$uid, $date]);
            $existing = (int)$stmt->fetchColumn();

            if ($existing) {
                $db->prepare('UPDATE schedule_entries SET shift_type=?, shift_start=?, shift_end=?, note=?, updated_at=NOW() WHERE id=?')
                   ->execute([$type, $start, $end, $note, $existing]);
                $id = $existing;
            } else {
                $db->prepare('INSERT INTO schedule_entries (user_id, entry_date, shift_type, shift_start, shift_end, note, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())')
                   ->execute([$uid, $date, $type, $start, $end, $note]);
                $id = (int)$db->lastInsertId();
            }

            if ($type === 'dyzur') {
                $hrs = ($start && $end) ? " ({$start}-{$end})" : '';
                create_notification($uid, 'dyzur', 'Przypisano dyżur', "Administrator przypisał Ci dyżur na dzień {$date}{$hrs}." . ($note ? " Notatka: {$note}" : ''), $date);
            }

            $label = '';
            $stTypes = get_shift_types();
            $stLabel = mb_strtolower($stTypes[$type]['label'] ?? '');
            $isNoTime = in_array($type, $noTimeTypes, true) || strpos($stLabel,'urlop')!==false || strpos($stLabel,'chorobow')!==false;
            if ($type === 'wolne') $label = 'W';
            elseif ($type === 'brak') $label = 'X';
            elseif ($isNoTime) $label = $stTypes[$type]['label'] ?? $type;
            elseif ($type === 'swieto') $label = $note ? mb_substr($note,0,8) : 'Święto';
            elseif ($type === 'dyzur') $label = ($start && $end) ? short_time($start).'-'.short_time($end) : 'DYŻUR';
            else $label = ($start && $end) ? short_time($start).'-'.short_time($end) : mb_substr(shift_label($type),0,6);

            $updated[] = [
                'id' => $id,
                'user_id' => $uid,
                'entry_date' => $date,
                'shift_type' => $type,
                'shift_start' => $start,
                'shift_end' => $end,
                'note' => $note,
                'label' => $label,
                'hours' => calc_hours($start, $end),
                'color' => shift_color($type),
                'text' => shift_text($type),
            ];
            $count++;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        json_out(['error'=>'Nie udało się zapisać zmian: '.$e->getMessage()], 400);
    }

    json_out(['ok'=>true, 'count'=>$count, 'updated'=>$updated]);
}

$uid   = (int)($_POST['user_id']??0);
$year  = (int)($_POST['year']??0);
$month = (int)($_POST['month']??0);
$mode  = $_POST['mode']??'weekdays'; // weekdays, all, empty_only
$workPattern = $_POST['work_pattern'] ?? 'custom';

if (!$uid || !$year || !$month) json_out(['error'=>'Brak danych'], 400);
if ($year < 2020 || $year > 2099 || $month < 1 || $month > 12) json_out(['error'=>'Nieprawidłowy miesiąc'], 400);

$dim = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$count = 0;

for ($d = 1; $d <= $dim; $d++) {
    $dow = (int)date('w', mktime(0,0,0, $month, $d, $year));
    $isWeekend = ($dow === 0 || $dow === 6);

    if ($mode === 'weekdays' && $isWeekend) continue;

    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);

    $stmt = $db->prepare('SELECT id FROM schedule_entries WHERE user_id=? AND entry_date=?');
    $stmt->execute([$uid, $date]);
    $existing = $stmt->fetchColumn();

    if ($mode === 'empty_only' && $existing) continue;

    $dayStart = $start;
    $dayEnd = $end;
    if ($workPattern === 'sck' && !$isWeekend && !in_array($type, $noTimeTypes, true)) {
        if ($dow >= 1 && $dow <= 4) {
            $dayStart = '07:30';
            $dayEnd = '16:00';
        } elseif ($dow === 5) {
            $dayStart = '07:30';
            $dayEnd = '13:30';
        }
    }

    if ($existing) {
        $db->prepare('UPDATE schedule_entries SET shift_type=?, shift_start=?, shift_end=?, note=?, updated_at=NOW() WHERE id=?')
           ->execute([$type, $dayStart, $dayEnd, $note, $existing]);
    } else {
        $db->prepare('INSERT INTO schedule_entries (user_id, entry_date, shift_type, shift_start, shift_end, note, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())')
           ->execute([$uid, $date, $type, $dayStart, $dayEnd, $note]);
    }
    $count++;

    if ($type === 'dyzur') {
        $hrs = ($dayStart && $dayEnd) ? " ({$dayStart}-{$dayEnd})" : '';
        create_notification($uid, 'dyzur', 'Przypisano dyżur', "Administrator przypisał Ci dyżur na dzień {$date}{$hrs}." . ($note ? " Notatka: {$note}" : ''), $date);
    }
}

json_out(['ok'=>true, 'count'=>$count]);
