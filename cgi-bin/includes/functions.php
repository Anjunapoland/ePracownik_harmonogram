<?php

function calc_hours(?string $start, ?string $end): float {
    if (!$start || !$end) return 0;
    $a = array_map('intval', explode(':', $start));
    $b = array_map('intval', explode(':', $end));
    $diff = ($b[0]*60+($b[1]??0)) - ($a[0]*60+($a[1]??0));
    return max(0, round($diff/60, 2));
}

/** Format TIME value: '07:30:00' → '7:30', '16:00:00' → '16:00' */
function short_time(?string $t): string {
    if (!$t) return '';
    $p = explode(':', $t);
    $h = intval($p[0]);
    return $h.':'.$p[1];  // hour as int (no leading zero) + minutes
}

function get_entries_for_month(int $year, int $month): array {
    $dim  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = sprintf('%04d-%02d-%02d', $year, $month, $dim);
    $stmt = get_db()->prepare('SELECT * FROM schedule_entries WHERE entry_date BETWEEN ? AND ?');
    $stmt->execute([$from, $to]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $day = (int) substr($r['entry_date'], 8, 2);
        $map[$r['user_id']][$day] = $r;
    }
    return $map;
}

function get_employees(): array {
    return get_db()->query("SELECT * FROM users WHERE active=1 ORDER BY department, full_name")->fetchAll();
}

function get_all_users(): array {
    return get_db()->query("SELECT * FROM users WHERE id > 0 ORDER BY role DESC, active DESC, full_name")->fetchAll();
}

function shift_label(string $t): string  { $st = get_shift_types(); return $st[$t]['label'] ?? $t; }
function shift_color(string $t): string  { $st = get_shift_types(); return $st[$t]['color'] ?? '#fff'; }
function shift_text(string $t): string   { $st = get_shift_types(); return $st[$t]['text']  ?? '#333'; }

// ---- Dynamic Shift Types ----

function get_shift_types(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db = get_db();
        $rows = $db->query('SELECT * FROM shift_types ORDER BY sort_order ASC, id ASC')->fetchAll();
        if ($rows && count($rows) > 0) {
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['code']] = [
                    'label' => $r['label'],
                    'color' => $r['color'],
                    'text'  => $r['text_color'],
                    'start' => $r['default_start'] ?? '',
                    'end'   => $r['default_end'] ?? '',
                ];
            }
            return $cache;
        }
    } catch (\Throwable $e) {}
    // Fallback to defaults
    $cache = DEFAULT_SHIFT_TYPES;
    return $cache;
}

function save_shift_type(string $code, string $label, string $color, string $textColor, string $defaultStart = '', string $defaultEnd = '', int $sortOrder = 0): void {
    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM shift_types WHERE code=?');
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE shift_types SET label=?, color=?, text_color=?, default_start=?, default_end=?, sort_order=? WHERE code=?')
           ->execute([$label, $color, $textColor, $defaultStart ?: null, $defaultEnd ?: null, $sortOrder, $code]);
    } else {
        $db->prepare('INSERT INTO shift_types (code, label, color, text_color, default_start, default_end, sort_order) VALUES (?,?,?,?,?,?,?)')
           ->execute([$code, $label, $color, $textColor, $defaultStart ?: null, $defaultEnd ?: null, $sortOrder]);
    }
}

function delete_shift_type(string $code): bool {
    // Protected types that cannot be deleted
    $protected = ['standard', 'wolne', 'brak'];
    if (in_array($code, $protected)) return false;
    $db = get_db();
    $db->prepare('DELETE FROM shift_types WHERE code=?')->execute([$code]);
    return true;
}

function seed_shift_types(): void {
    $db = get_db();
    $count = (int)$db->query('SELECT COUNT(*) FROM shift_types')->fetchColumn();
    if ($count > 0) return;
    $order = 0;
    foreach (DEFAULT_SHIFT_TYPES as $code => $s) {
        $db->prepare('INSERT IGNORE INTO shift_types (code, label, color, text_color, default_start, default_end, sort_order) VALUES (?,?,?,?,?,?,?)')
           ->execute([$code, $s['label'], $s['color'], $s['text'], $s['start'] ?: null, $s['end'] ?: null, $order++]);
    }
}

function month_hours(array $ue, int $dim): float {
    $total = 0;
    $types = get_shift_types();
    for ($d = 1; $d <= $dim; $d++) {
        if (isset($ue[$d])) {
            $e = $ue[$d];
            $stDef = $types[$e['shift_type']] ?? [];
            $start = $e['shift_start'] ?: ($stDef['start'] ?? null);
            $end   = $e['shift_end']   ?: ($stDef['end']   ?? null);
            $total += calc_hours($start, $end);
        }
    }
    return $total;
}

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Notifications ----

function create_notification(int $userId, string $type, string $title, string $body = '', ?string $relatedDate = null): void {
    get_db()->prepare('INSERT INTO notifications (user_id, type, title, body, related_date) VALUES (?,?,?,?,?)')
            ->execute([$userId, $type, $title, $body, $relatedDate]);
}

function get_unread_count(int $userId): int {
    $stmt = get_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function get_notifications(int $userId, int $limit = 20): array {
    $stmt = get_db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function mark_notifications_read(int $userId): void {
    get_db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0')->execute([$userId]);
}

// ---- Dyżury ----

function get_user_dyzury(int $userId, int $limit = 10): array {
    $stmt = get_db()->prepare(
        "SELECT * FROM schedule_entries WHERE user_id=? AND shift_type='dyzur' AND entry_date >= CURDATE() ORDER BY entry_date ASC LIMIT ?"
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// ---- Settings ----

function get_setting(string $key, string $default = ''): string {
    $stmt = get_db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function set_setting(string $key, string $value): void {
    $db = get_db();
    $stmt = $db->prepare('SELECT 1 FROM settings WHERE setting_key=?');
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        $db->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?')->execute([$value, $key]);
    } else {
        $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?)')->execute([$key, $value]);
    }
}

// ---- Departments ----

function get_departments(): array {
    return get_db()->query("SELECT DISTINCT department FROM users WHERE active=1 ORDER BY department")->fetchAll(\PDO::FETCH_COLUMN);
}

// Can employee view all schedules?
function employee_can_view_all(array $user): bool {
    // Global setting overrides individual
    $globalMode = get_setting('employee_view_mode', 'own');
    if ($globalMode === 'own') return false; // globally restricted
    // If global is 'individual', check per-user flag
    return (bool) ($user['can_view_all'] ?? false);
}

/**
 * Check 11-hour rest rule violations (art. 132 KP).
 * Returns: $violations[user_id][day] = ['gap'=>float, 'prev_end'=>string, 'next_start'=>string]
 */
function check_rest_violations(array $entries, int $year, int $month, int $dim): array {
    $noTimeCodes = ['urlop','urlop_na_zadanie','chorobowe','wolne','brak'];
    $stTypes = get_shift_types();
    $violations = [];
    $db = get_db();

    foreach ($entries as $uid => $days) {
        $prevEnd = null;
        // Check day 1 against previous month last day
        $prevMonthDay = date('Y-m-d', mktime(0,0,0,$month,0,$year));
        $stmt = $db->prepare('SELECT shift_end, shift_type FROM schedule_entries WHERE user_id=? AND entry_date=?');
        $stmt->execute([$uid, $prevMonthDay]);
        $prevRow = $stmt->fetch();
        if ($prevRow && $prevRow['shift_end']) {
            $pst = $prevRow['shift_type'];
            $pLbl = mb_strtolower($stTypes[$pst]['label'] ?? '');
            $pNoTime = in_array($pst, $noTimeCodes) || strpos($pLbl, 'urlop') !== false || strpos($pLbl, 'chorobow') !== false;
            if (!$pNoTime) $prevEnd = $prevRow['shift_end'];
        }

        for ($d = 1; $d <= $dim; $d++) {
            $e = $days[$d] ?? null;
            if (!$e) { $prevEnd = null; continue; }
            $st = $e['shift_type'];
            $stLbl = mb_strtolower($stTypes[$st]['label'] ?? '');
            $isNoTime = in_array($st, $noTimeCodes) || strpos($stLbl, 'urlop') !== false || strpos($stLbl, 'chorobow') !== false;
            $curStart = $e['shift_start'] ?? null;
            $curEnd = $e['shift_end'] ?? null;
            if ($isNoTime) { $prevEnd = null; continue; }
            if ($prevEnd && $curStart) {
                $pe = explode(':', $prevEnd);
                $cs = explode(':', $curStart);
                $restMin = (24*60 - intval($pe[0])*60 - intval($pe[1]??0)) + intval($cs[0])*60 + intval($cs[1]??0);
                if ($restMin < 11*60) {
                    $violations[$uid][$d] = [
                        'gap' => round($restMin/60, 1),
                        'prev_end' => short_time($prevEnd),
                        'next_start' => short_time($curStart)
                    ];
                }
            }
            $prevEnd = $curEnd;
        }
    }
    return $violations;
}

/**
 * Check rest violation for a single save.
 */
function check_single_rest(int $uid, string $date, ?string $start, ?string $end): array {
    if (!$start || !$end) return [];
    $warnings = [];
    $db = get_db();
    $noTimeCodes = ['urlop','urlop_na_zadanie','chorobowe','wolne','brak'];
    $stTypes = get_shift_types();

    // vs previous day
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
    $stmt = $db->prepare('SELECT shift_end, shift_type FROM schedule_entries WHERE user_id=? AND entry_date=?');
    $stmt->execute([$uid, $prevDate]);
    $prev = $stmt->fetch();
    if ($prev && $prev['shift_end']) {
        $pLbl = mb_strtolower($stTypes[$prev['shift_type']]['label'] ?? '');
        $pNo = in_array($prev['shift_type'], $noTimeCodes) || strpos($pLbl,'urlop')!==false || strpos($pLbl,'chorobow')!==false;
        if (!$pNo) {
            $pe=explode(':',$prev['shift_end']); $cs=explode(':',$start);
            $r=(24*60-intval($pe[0])*60-intval($pe[1]??0))+intval($cs[0])*60+intval($cs[1]??0);
            if ($r<11*60) $warnings[]='Przerwa od poprzedniej zmiany: '.round($r/60,1).'h (min. 11h wg art. 132 KP). Poprzednia zmiana konczy sie o '.short_time($prev['shift_end']).'.';
        }
    }
    // vs next day
    $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
    $stmt = $db->prepare('SELECT shift_start, shift_type FROM schedule_entries WHERE user_id=? AND entry_date=?');
    $stmt->execute([$uid, $nextDate]);
    $next = $stmt->fetch();
    if ($next && $next['shift_start']) {
        $nLbl = mb_strtolower($stTypes[$next['shift_type']]['label'] ?? '');
        $nNo = in_array($next['shift_type'], $noTimeCodes) || strpos($nLbl,'urlop')!==false || strpos($nLbl,'chorobow')!==false;
        if (!$nNo) {
            $ee=explode(':',$end); $ns=explode(':',$next['shift_start']);
            $r=(24*60-intval($ee[0])*60-intval($ee[1]??0))+intval($ns[0])*60+intval($ns[1]??0);
            if ($r<11*60) $warnings[]='Przerwa do nastepnej zmiany: '.round($r/60,1).'h (min. 11h wg art. 132 KP). Nastepna zmiana zaczyna sie o '.short_time($next['shift_start']).'.';
        }
    }
    return $warnings;
}
