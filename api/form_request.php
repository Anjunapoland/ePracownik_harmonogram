<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u) json_out(['error'=>'Brak autoryzacji'], 401);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$action = $_POST['action'] ?? '';
$db = get_db();

// ── SUBMIT (employee sends form) ──
if ($action === 'submit') {
    if (get_setting('form_submissions_enabled', '0') !== '1') {
        json_out(['error'=>'Wysyłanie formularzy jest wyłączone'], 400);
    }
    $formType = $_POST['form_type'] ?? '';
    $formData = $_POST['form_data'] ?? '';
    if (!$formType || !$formData) json_out(['error'=>'Brak danych'], 400);

    // Find approvers for this employee
    $stmt = $db->prepare('SELECT approver_id FROM form_approvers WHERE employee_id=?');
    $stmt->execute([$u['id']]);
    $approvers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($approvers)) {
        json_out(['error'=>'Nie przypisano osoby akceptującej dla Twojego konta. Skontaktuj się z administratorem.'], 400);
    }

    $db->prepare('INSERT INTO form_requests (user_id, form_type, form_data, status, created_at) VALUES (?,?,?,\'pending\',NOW())')
       ->execute([$u['id'], $formType, $formData]);
    $reqId = $db->lastInsertId();

    // Notify all approvers
    $formLabels = [
        'leave' => 'Wniosek o urlop wypoczynkowy',
        'overtime' => 'Wniosek o czas wolny za nadgodziny',
        'wifi' => 'Oświadczenie Wi-Fi SCK'
    ];
    $label = $formLabels[$formType] ?? $formType;
    foreach ($approvers as $aid) {
        create_notification(
            (int)$aid,
            'form_request',
            'Nowy wniosek do akceptacji',
            $u['full_name'] . ' złożył(a): ' . $label,
            null
        );
        // Email notification to approver
        if (get_setting('email_notify_admin', '0') === '1') {
            $stAdm = $db->prepare('SELECT email, full_name FROM users WHERE id=?');
            $stAdm->execute([(int)$aid]);
            $admRow = $stAdm->fetch();
            if ($admRow && $admRow['email']) {
                send_notification_email(
                    $admRow['email'],
                    'Nowy wniosek do akceptacji - ' . $u['full_name'],
                    "Pracownik " . $u['full_name'] . " zlozyl(a) nowy wniosek:\n" . $label . "\n\nZaloguj sie do systemu harmonogram.sck.strzegom.pl aby go rozpatrzyc."
                );
            }
        }
    }

    json_out(['ok'=>true, 'id'=>$reqId]);
}

// ── APPROVE (admin accepts) ──
if ($action === 'approve') {
    if ($u['role'] !== 'admin' && $u['role'] !== 'kadry') json_out(['error'=>'Brak uprawnień'], 403);
    $reqId = (int)($_POST['request_id'] ?? 0);
    if (!$reqId) json_out(['error'=>'Brak ID'], 400);

    $stmt = $db->prepare('SELECT * FROM form_requests WHERE id=? AND status=\'pending\'');
    $stmt->execute([$reqId]);
    $req = $stmt->fetch();
    if (!$req) json_out(['error'=>'Wniosek nie istnieje lub został już rozpatrzony'], 404);

    // Check this admin is an approver for this employee
    $stmt2 = $db->prepare('SELECT id FROM form_approvers WHERE employee_id=? AND approver_id=?');
    $stmt2->execute([$req['user_id'], $u['id']]);
    if (!$stmt2->fetchColumn()) json_out(['error'=>'Nie jesteś osobą akceptującą dla tego pracownika'], 403);

    $db->prepare('UPDATE form_requests SET status=\'approved\', decided_by=?, decided_at=NOW() WHERE id=? AND status=\'pending\'')
       ->execute([$u['id'], $reqId]);

    // Auto-deduct leave/overtime balances
    $formData = json_decode($req['form_data'], true) ?: [];
    $currentYear = (int)date('Y');

    if ($req['form_type'] === 'leave') {
        $days = 0;
        if (isset($formData['Liczba dni'])) $days = (int)$formData['Liczba dni'];
        if ($days > 0) {
            // Deduct: first from prev year, then current
            $stBal = $db->prepare('SELECT id, leave_prev_year, leave_current_year FROM leave_balances WHERE user_id=? AND year=?');
            $stBal->execute([$req['user_id'], $currentYear]);
            $bal = $stBal->fetch();
            if ($bal) {
                $prev = (float)$bal['leave_prev_year'];
                $curr = (float)$bal['leave_current_year'];
                $rem = $days;
                $fromPrev = min($prev, $rem);
                $prev -= $fromPrev;
                $rem -= $fromPrev;
                $fromCurr = min($curr, $rem);
                $curr -= $fromCurr;
                $db->prepare('UPDATE leave_balances SET leave_prev_year=?, leave_current_year=?, updated_at=NOW() WHERE id=?')
                   ->execute([$prev, $curr, $bal['id']]);
            }
        }
    }

    if ($req['form_type'] === 'overtime') {
        // Parse total hours to deduct from form data
        $sumText = $formData['Razem do odbioru'] ?? '';
        preg_match('/[\d,.]+/', $sumText, $m);
        $hours = $m ? (float)str_replace(',', '.', $m[0]) : 0;
        if ($hours > 0) {
            $stBal = $db->prepare('SELECT id, overtime_hours FROM leave_balances WHERE user_id=? AND year=?');
            $stBal->execute([$req['user_id'], $currentYear]);
            $bal = $stBal->fetch();
            if ($bal) {
                $newOt = max(0, (float)$bal['overtime_hours'] - $hours);
                $db->prepare('UPDATE leave_balances SET overtime_hours=?, updated_at=NOW() WHERE id=?')
                   ->execute([$newOt, $bal['id']]);
            }
        }
    }

    // Generate PDF
    $pdfFile = generate_request_pdf($req, $u['full_name']);

    // Notify employee
    $formLabels = ['leave'=>'Wniosek o urlop','overtime'=>'Wniosek o czas wolny za nadgodziny','wifi'=>'Oświadczenie Wi‑Fi'];
    $employeeFormLabel = $formLabels[$req['form_type']] ?? $req['form_type'];
    create_notification(
        (int)$req['user_id'],
        'form_approved',
        'Wniosek zaakceptowany',
        $employeeFormLabel . ' został zaakceptowany przez ' . $u['full_name'] . '.',
        null
    );

    // Notify all kadry users
    $kadryUsers = $db->query("SELECT id, email, full_name FROM users WHERE role='kadry' AND active=1")->fetchAll();
    $empName = $db->prepare('SELECT full_name FROM users WHERE id=?');
    $empName->execute([$req['user_id']]);
    $eName = $empName->fetchColumn() ?: '?';
    $formLabels = ['leave'=>'Wniosek o urlop','overtime'=>'Czas wolny za nadgodziny','wifi'=>'Oświadczenie Wi-Fi'];
    $fLabel = $formLabels[$req['form_type']] ?? $req['form_type'];
    foreach ($kadryUsers as $kUser) {
        create_notification(
            (int)$kUser['id'],
            'form_kadry',
            'Zaakceptowany wniosek do archiwizacji',
            $eName . ': ' . $fLabel . ' — zaakceptowany przez ' . $u['full_name'] . ' dnia ' . date('d.m.Y H:i'),
            null
        );
        // Email notification to kadry
        if (get_setting('email_notify_kadry', '0') === '1' && $kUser['email']) {
            $formDataText = '';
            foreach (json_decode($req['form_data'], true) ?: [] as $k => $v) {
                if ($v) $formDataText .= $k . ': ' . $v . "\n";
            }
            send_notification_email(
                $kUser['email'],
                'Zaakceptowany wniosek - ' . $eName,
                "Wniosek pracownika " . $eName . " zostal zaakceptowany.\n\nTyp: " . $fLabel . "\nZaakceptowal(a): " . $u['full_name'] . "\nData: " . date('d.m.Y H:i') . "\n\nDane wniosku:\n" . $formDataText . "\nZaloguj sie do systemu harmonogram.sck.strzegom.pl"
            );
        }
    }

    json_out(['ok'=>true]);
}

// ── REJECT (admin rejects) ──
if ($action === 'reject') {
    if ($u['role'] !== 'admin' && $u['role'] !== 'kadry') json_out(['error'=>'Brak uprawnień'], 403);
    $reqId = (int)($_POST['request_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if (!$reqId) json_out(['error'=>'Brak ID'], 400);
    if (!$reason) json_out(['error'=>'Podaj uzasadnienie odmowy'], 400);

    $stmt = $db->prepare('SELECT * FROM form_requests WHERE id=? AND status=\'pending\'');
    $stmt->execute([$reqId]);
    $req = $stmt->fetch();
    if (!$req) json_out(['error'=>'Wniosek nie istnieje lub został już rozpatrzony'], 404);

    $stmt2 = $db->prepare('SELECT id FROM form_approvers WHERE employee_id=? AND approver_id=?');
    $stmt2->execute([$req['user_id'], $u['id']]);
    if (!$stmt2->fetchColumn()) json_out(['error'=>'Nie jesteś osobą akceptującą dla tego pracownika'], 403);

    $db->prepare('UPDATE form_requests SET status=\'rejected\', decided_by=?, decided_at=NOW(), reject_reason=? WHERE id=? AND status=\'pending\'')
       ->execute([$u['id'], $reason, $reqId]);

    $formLabels = ['leave'=>'Wniosek o urlop','overtime'=>'Wniosek o czas wolny za nadgodziny','wifi'=>'Oświadczenie Wi‑Fi'];
    $employeeFormLabel = $formLabels[$req['form_type']] ?? $req['form_type'];
    create_notification(
        (int)$req['user_id'],
        'form_rejected',
        'Wniosek odrzucony',
        $employeeFormLabel . ' został odrzucony przez ' . $u['full_name'] . '. Powód: ' . $reason,
        null
    );

    json_out(['ok'=>true]);
}

json_out(['error'=>'Nieznana akcja'], 400);
