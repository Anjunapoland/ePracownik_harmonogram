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


function ensure_users_profile_columns(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $db = get_db();
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'job_title'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE users ADD COLUMN job_title VARCHAR(191) NOT NULL DEFAULT '' AFTER full_name");
    }
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


function get_wolne_overtime_delta_hours(string $date): float {
    $ts = strtotime($date);
    if ($ts === false) return 0.0;
    $dow = (int)date('w', $ts); // 0=nd, 1=pn, ... 5=pt
    if ($dow >= 1 && $dow <= 4) return 8.5;
    if ($dow === 5) return 6.0;
    return 0.0;
}

function apply_wolne_overtime_balance_change(int $userId, string $date, ?string $oldType, ?string $newType): void {
    $oldIsWolne = ((string)$oldType === 'wolne');
    $newIsWolne = ((string)$newType === 'wolne');
    if ($oldIsWolne === $newIsWolne) return;

    $hours = get_wolne_overtime_delta_hours($date);
    if ($hours <= 0) return;

    // Dodanie "wolne (W)" odejmuje godziny, zdjęcie takiego dnia oddaje godziny.
    $delta = $newIsWolne ? -$hours : $hours;
    $year = (int)date('Y', strtotime($date));

    $db = get_db();
    $db->prepare('INSERT IGNORE INTO leave_balances (user_id, year, overtime_hours, created_at, updated_at) VALUES (?,?,0,NOW(),NOW())')
       ->execute([$userId, $year]);
    $db->prepare('UPDATE leave_balances SET overtime_hours = ROUND(overtime_hours + ?, 1), updated_at=NOW() WHERE user_id=? AND year=?')
       ->execute([$delta, $userId, $year]);
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

function delete_notifications_by(int $userId, array $types, ?string $relatedDate = null): void {
    if (empty($types)) return;
    $ph = implode(',', array_fill(0, count($types), '?'));
    $sql = "DELETE FROM notifications WHERE user_id=? AND type IN ($ph)";
    $params = array_merge([$userId], array_values($types));
    if ($relatedDate !== null) {
        $sql .= " AND related_date=?";
        $params[] = $relatedDate;
    }
    get_db()->prepare($sql)->execute($params);
}

function build_schedule_notification_payload(string $type, string $date, ?string $start = null, ?string $end = null, string $note = ''): array {
    $stTypes = get_shift_types();
    $typeLabel = $stTypes[$type]['label'] ?? $type;
    $hoursText = '';
    if ($start && $end) {
        $hoursText = ' (' . short_time($start) . '–' . short_time($end) . ')';
    }

    if ($type === 'dyzur') {
        return [
            'type' => 'dyzur',
            'title' => 'Zmiana w harmonogramie',
            'body' => 'Przypisano Ci dyżur na dzień ' . $date . $hoursText . ($note ? '. Notatka: ' . $note : '') . '.',
        ];
    }

    if ($type === 'brak') {
        return [
            'type' => 'schedule_change',
            'title' => 'Zmiana w harmonogramie',
            'body' => 'Zaktualizowano Twój grafik na dzień ' . $date . ': brak dyżuru' . ($note ? '. Notatka: ' . $note : '') . '.',
        ];
    }

    return [
        'type' => 'schedule_change',
        'title' => 'Zmiana w harmonogramie',
        'body' => 'Zaktualizowano Twój grafik na dzień ' . $date . ': ' . $typeLabel . $hoursText . ($note ? '. Notatka: ' . $note : '') . '.',
    ];
}

function create_or_replace_schedule_notification(int $userId, string $type, string $date, ?string $start = null, ?string $end = null, string $note = ''): void {
    delete_notifications_by($userId, ['dyzur', 'schedule_change'], $date);
    $payload = build_schedule_notification_payload($type, $date, $start, $end, $note);
    create_notification($userId, $payload['type'], $payload['title'], $payload['body'], $date);
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

function clear_notifications(int $userId): void {
    get_db()->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$userId]);
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

/**
 * Calculate remaining leave after deducting used days.
 * Logic: first deduct from prev year (zaległy), then from current year (bieżący).
 * Returns ['prev_remain'=>float, 'curr_remain'=>float, 'used'=>int, 'total'=>float]
 */
function calc_leave_remaining(int $userId, int $year): array {
    $db = get_db();
    $stmt = $db->prepare('SELECT leave_prev_year, leave_current_year FROM leave_balances WHERE user_id=? AND year=?');
    $stmt->execute([$userId, $year]);
    $lb = $stmt->fetch();
    $prevPool = $lb ? (float)$lb['leave_prev_year'] : 0;
    $currPool = $lb ? (float)$lb['leave_current_year'] : 0;
    $total = $prevPool + $currPool;

    // Count used leave days
    $yearStart = sprintf('%04d-01-01', $year);
    $yearEnd = sprintf('%04d-12-31', $year);
    $stTypes = get_shift_types();
    $leaveCodes = [];
    foreach ($stTypes as $code => $st) {
        $lbl = mb_strtolower($st['label']);
        if (in_array($code, ['urlop','urlop_na_zadanie']) || strpos($lbl, 'urlop') !== false) $leaveCodes[] = $code;
    }
    $used = 0;
    if (!empty($leaveCodes)) {
        $ph = implode(',', array_fill(0, count($leaveCodes), '?'));
        $stmtU = $db->prepare("SELECT COUNT(*) FROM schedule_entries WHERE user_id=? AND entry_date BETWEEN ? AND ? AND shift_type IN ($ph)");
        $stmtU->execute(array_merge([$userId, $yearStart, $yearEnd], $leaveCodes));
        $used = (int)$stmtU->fetchColumn();
    }

    // Deduct: first from prev year, then from current
    $remaining = $used;
    $prevRemain = $prevPool;
    $currRemain = $currPool;
    if ($remaining > 0) {
        $fromPrev = min($prevRemain, $remaining);
        $prevRemain -= $fromPrev;
        $remaining -= $fromPrev;
    }
    if ($remaining > 0) {
        $fromCurr = min($currRemain, $remaining);
        $currRemain -= $fromCurr;
        $remaining -= $fromCurr;
    }

    return [
        'prev_pool' => $prevPool,
        'curr_pool' => $currPool,
        'prev_remain' => $prevRemain,
        'curr_remain' => $currRemain,
        'used' => $used,
        'total' => $total,
        'remain' => $prevRemain + $currRemain
    ];
}

/**
 * Check if form submissions are enabled and user has approvers.
 */
function can_submit_form(int $userId): bool {
    if (get_setting('form_submissions_enabled', '0') !== '1') return false;
    $stmt = get_db()->prepare('SELECT COUNT(*) FROM form_approvers WHERE employee_id=?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Get pending request count for employee.
 */
function get_pending_requests_count(int $userId): int {
    $stmt = get_db()->prepare("SELECT COUNT(*) FROM form_requests WHERE user_id=? AND status='pending'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Send email notification (uses PHP mail()).
 */
function send_notification_email(string $to, string $subject, string $body): bool {
    $headers  = "From: SCK Harmonogram <noreply@sck.strzegom.pl>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#1c1917;max-width:600px;margin:0 auto">';
    $html .= '<div style="background:linear-gradient(135deg,#ea580c,#f97316);padding:16px 24px;border-radius:12px 12px 0 0">';
    $html .= '<h2 style="margin:0;color:#fff;font-size:16px">SCK Harmonogram</h2></div>';
    $html .= '<div style="background:#fff;border:1px solid #e7e5e4;border-top:none;border-radius:0 0 12px 12px;padding:20px 24px">';
    $html .= '<h3 style="margin:0 0 12px;color:#1c1917">' . htmlspecialchars($subject) . '</h3>';
    $html .= '<p style="margin:0;line-height:1.6;color:#57534e">' . nl2br(htmlspecialchars($body)) . '</p>';
    $html .= '<hr style="border:none;border-top:1px solid #f5f5f4;margin:20px 0">';
    $html .= '<p style="font-size:11px;color:#a8a29e;margin:0">Ta wiadomosc zostala wyslana automatycznie z systemu SCK Harmonogram.<br>';
    $html .= '<a href="https://harmonogram.sck.strzegom.pl" style="color:#ea580c">harmonogram.sck.strzegom.pl</a></p>';
    $html .= '</div></body></html>';

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
}

function send_notification_email_with_attachment(string $to, string $subject, string $body, string $attachmentPath, string $attachmentName = 'wniosek.pdf'): bool {
    if (!is_file($attachmentPath)) {
        return send_notification_email($to, $subject, $body);
    }

    $boundary = '=_Part_' . md5((string)microtime(true));
    $headers  = "From: SCK Harmonogram <noreply@sck.strzegom.pl>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#1c1917;max-width:600px;margin:0 auto">';
    $html .= '<div style="background:linear-gradient(135deg,#ea580c,#f97316);padding:16px 24px;border-radius:12px 12px 0 0">';
    $html .= '<h2 style="margin:0;color:#fff;font-size:16px">SCK Harmonogram</h2></div>';
    $html .= '<div style="background:#fff;border:1px solid #e7e5e4;border-top:none;border-radius:0 0 12px 12px;padding:20px 24px">';
    $html .= '<h3 style="margin:0 0 12px;color:#1c1917">' . htmlspecialchars($subject) . '</h3>';
    $html .= '<p style="margin:0;line-height:1.6;color:#57534e">' . nl2br(htmlspecialchars($body)) . '</p>';
    $html .= '<hr style="border:none;border-top:1px solid #f5f5f4;margin:20px 0">';
    $html .= '<p style="font-size:11px;color:#a8a29e;margin:0">W załączniku znajduje się plik PDF z wnioskiem.</p>';
    $html .= '</div></body></html>';

    $attachment = chunk_split(base64_encode((string)file_get_contents($attachmentPath)));

    $eol = "\r\n";
    $message  = "--" . $boundary . $eol;
    $message .= "Content-Type: text/html; charset=UTF-8" . $eol;
    $message .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
    $message .= $html . $eol;

    $message .= "--" . $boundary . $eol;
    $message .= "Content-Type: application/pdf; name=\"" . $attachmentName . "\"" . $eol;
    $message .= "Content-Disposition: attachment; filename=\"" . $attachmentName . "\"" . $eol;
    $message .= "Content-Transfer-Encoding: base64" . $eol . $eol;
    $message .= $attachment . $eol;
    $message .= "--" . $boundary . "--";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headers);
}

function request_pdf_escape(string $text): string {
    return str_replace(array('\\', '(', ')'), array('\\\\', '\(', '\)'), $text);
}

function request_pdf_safe_text(string $text): string {
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($ascii === false || $ascii === '') {
        $ascii = preg_replace('/[^\x20-\x7E]/', ' ', $text) ?? '';
    }
    $ascii = str_replace(array("\n", "\r", "\t"), ' ', $ascii);
    $ascii = preg_replace('/[^\x20-\x7E]/', ' ', $ascii) ?? '';
    $ascii = preg_replace('/\s+/', ' ', trim($ascii)) ?? '';
    return request_pdf_escape($ascii);
}

function generate_request_pdf(array $req, string $approverName): string {
    $data = json_decode($req['form_data'], true) ?: [];
    $formLabels = ['leave'=>'Wniosek o urlop wypoczynkowy','overtime'=>'Wniosek o czas wolny za nadgodziny','wifi'=>'Oświadczenie Wi‑Fi SCK'];
    $title = $formLabels[$req['form_type']] ?? $req['form_type'];

    $db = get_db();
    $emp = $db->prepare('SELECT full_name FROM users WHERE id=?');
    $emp->execute([$req['user_id']]);
    $empName = (string)($emp->fetchColumn() ?: 'Nieznany');

    $lines = array();
    $lines[] = $title;
    $lines[] = 'Pracownik: ' . $empName;
    $lines[] = str_repeat('-', 82);
    foreach ($data as $k => $v) {
        if ($v === null || $v === '') continue;
        $lines[] = trim((string)$k) . ': ' . trim((string)$v);
    }
    $lines[] = str_repeat('-', 82);
    $lines[] = 'ZAAKCEPTOWANY';
    $lines[] = 'Zaakceptowal(a): ' . $approverName;
    $lines[] = 'Data: ' . date('d.m.Y H:i');

    $chunks = array_chunk($lines, 48);
    $objects = array();
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

    $pageObjectIds = array();
    $contentObjectIds = array();
    $fontObjectId = 3;
    $nextObjectId = 4;

    foreach ($chunks as $chunk) {
        $stream = "BT\n/F1 10 Tf\n40 800 Td\n14 TL\n";
        foreach ($chunk as $line) {
            $stream .= '(' . request_pdf_safe_text($line) . ") Tj\nT*\n";
        }
        $stream .= 'ET';

        $contentId = $nextObjectId++;
        $pageId = $nextObjectId++;
        $contentObjectIds[] = $contentId;
        $pageObjectIds[] = $pageId;

        $objects[$contentId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
    }
    $lines[] = str_repeat('-', 88);
    $lines[] = 'ZAAKCEPTOWANY';
    $lines[] = 'Zaakceptowal(a): ' . $approverName;
    $lines[] = 'Data: ' . date('d.m.Y H:i');

    $pdf = '';

    if (function_exists('imagecreatetruecolor') && function_exists('imagestring') && function_exists('imagejpeg')) {
        $w = 1240;
        $h = 1754;
        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 24, 24, 24);
        $green = imagecolorallocate($im, 22, 163, 74);
        $greenBg = imagecolorallocate($im, 240, 253, 244);
        imagefill($im, 0, 0, $white);

        $y = 80;
        imagestring($im, 5, 70, $y, request_pdf_safe_text($title), $black);
        $y += 34;
        imagestring($im, 4, 70, $y, request_pdf_safe_text('Pracownik: ' . $empName), $black);
        $y += 30;

        foreach ($lines as $idx => $line) {
            if ($idx < 2) continue;
            if ($y > 1450) break;
            imagestring($im, 3, 70, $y, request_pdf_safe_text($line), $black);
            $y += 20;
        }

    $kidsParts = array();
    foreach ($pageObjectIds as $id) {
        $kidsParts[] = $id . ' 0 R';
    }
    $kids = implode(' ', $kidsParts);

    $objects[2] = '<< /Type /Pages /Kids [ ' . $kids . ' ] /Count ' . count($pageObjectIds) . ' >>';
    $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    foreach ($pageObjectIds as $idx => $pageId) {
        $contentId = $contentObjectIds[$idx];
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObjectId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = array(0 => 0);
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $maxId = max(array_keys($objects));
    $pdf .= 'xref' . "\n" . '0 ' . ($maxId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxId; $i++) {
        $off = isset($offsets[$i]) ? $offsets[$i] : 0;
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= 'trailer' . "\n" . '<< /Size ' . ($maxId + 1) . ' /Root 1 0 R >>' . "\n";
    $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF";

    $dir = __DIR__ . '/../storage/requests';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'wniosek_' . $req['id'] . '_' . date('Ymd_His') . '.pdf';
    $filepath = $dir . '/' . $filename;
    file_put_contents($filepath, $pdf);

    $expires = date('Y-m-d', strtotime('+183 days'));
    $db->prepare('UPDATE form_requests SET pdf_file=?, expires_at=? WHERE id=?')->execute([$filename, $expires, $req['id']]);

    return $filename;
}

/**
 * Clean expired request PDFs.
 */
function cleanup_expired_requests(): int {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, pdf_file FROM form_requests WHERE expires_at IS NOT NULL AND expires_at < CURDATE() AND pdf_file IS NOT NULL");
    $stmt->execute();
    $expired = $stmt->fetchAll();
    $count = 0;
    $dir = __DIR__ . '/../storage/requests';
    foreach ($expired as $r) {
        $file = $dir . '/' . $r['pdf_file'];
        if (file_exists($file)) @unlink($file);
        $db->prepare('UPDATE form_requests SET pdf_file=NULL WHERE id=?')->execute([$r['id']]);
        $count++;
    }
    return $count;
}
