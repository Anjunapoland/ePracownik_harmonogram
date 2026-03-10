<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
if (!is_super_admin()) { header('Location: schedule.php'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['_token']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['_token'])) {
        $formsVisible = isset($_POST['forms_visible']) ? '1' : '0';
        set_setting('forms_visible', $formsVisible);

        $formSub = isset($_POST['form_submissions_enabled']) ? '1' : '0';
        set_setting('form_submissions_enabled', $formSub);

        $emailAdmin = isset($_POST['email_notify_admin']) ? '1' : '0';
        set_setting('email_notify_admin', $emailAdmin);

        $emailKadry = isset($_POST['email_notify_kadry']) ? '1' : '0';
        set_setting('email_notify_kadry', $emailKadry);

        $empView = $_POST['employee_view_mode'] ?? 'own';
        if (in_array($empView, ['own', 'all', 'individual'])) {
            set_setting('employee_view_mode', $empView);
        }

        $dashVis = isset($_POST['dashboard_visible']) ? '1' : '0';
        set_setting('dashboard_visible', $dashVis);

        // Save approver mappings
        if (isset($_POST['approvers'])) {
            $db = get_db();
            $db->exec('DELETE FROM form_approvers');
            $stIns = $db->prepare('INSERT IGNORE INTO form_approvers (employee_id, approver_id) VALUES (?,?)');
            foreach ($_POST['approvers'] as $empId => $approverIds) {
                foreach ((array)$approverIds as $aid) {
                    if ((int)$aid > 0) $stIns->execute([(int)$empId, (int)$aid]);
                }
            }
        }

        $saved = true;
    }

    // Announcement actions (separate from main form)
    if (!empty($_POST['ann_action'])) {
        $db = get_db();
        if ($_POST['ann_action'] === 'add') {
            $aTitle = trim($_POST['ann_title'] ?? '');
            $aBody = trim($_POST['ann_body'] ?? '');
            $aImp = isset($_POST['ann_important']) ? 1 : 0;
            $aEmail = isset($_POST['ann_send_email']) ? true : false;
            if ($aTitle && $aBody) {
                $db->prepare('INSERT INTO announcements (title, body, is_important, created_by) VALUES (?,?,?,?)')->execute([$aTitle, $aBody, $aImp, $user['id']]);
                $saved = true; $msg_ann = 'Ogłoszenie dodane';
                // Send email to all active employees
                if ($aEmail) {
                    $allEmails = $db->query("SELECT email FROM users WHERE active=1 AND email != ''")->fetchAll(PDO::FETCH_COLUMN);
                    $prefix = $aImp ? '[WAŻNE] ' : '';
                    foreach ($allEmails as $em) {
                        send_notification_email($em, $prefix . $aTitle, $aBody);
                    }
                    $msg_ann .= ' + e-mail wysłany do ' . count($allEmails) . ' osób';
                }
            }
        }
        if ($_POST['ann_action'] === 'delete') {
            $aId = (int)($_POST['ann_id'] ?? 0);
            if ($aId) { $db->prepare('DELETE FROM announcements WHERE id=?')->execute([$aId]); $saved = true; $msg_ann = 'Ogłoszenie usunięte'; }
        }
    }
}

$formsVisible = get_setting('forms_visible', '1') === '1';
$formSubEnabled = get_setting('form_submissions_enabled', '0') === '1';
$emailAdminEnabled = get_setting('email_notify_admin', '0') === '1';
$emailKadryEnabled = get_setting('email_notify_kadry', '0') === '1';
$empViewMode = get_setting('employee_view_mode', 'own');
$dashVisibleSetting = get_setting('dashboard_visible', '1') === '1';

// Load announcements
$annList = get_db()->query("SELECT a.*, u.full_name AS author FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.created_at DESC LIMIT 20")->fetchAll();

// Load all users and current approver mappings
$db = get_db();
$allUsers = $db->query("SELECT id, full_name, email, role FROM users WHERE active=1 ORDER BY full_name")->fetchAll();
$admins = array_filter($allUsers, function($u){ return $u['role']==='admin' || $u['role']==='kadry'; });
$allForApproval = $allUsers; // All users can submit forms
$approverMap = [];
foreach ($db->query('SELECT employee_id, approver_id FROM form_approvers')->fetchAll() as $r) {
    $approverMap[(int)$r['employee_id']][] = (int)$r['approver_id'];
}

layout_start('Ustawienia');
?>

<style>
.set-wrap{max-width:640px;margin:0 auto;padding:16px 24px}
.set-header{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.set-hicon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.set-hicon svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.set-htitle{font-size:24px;font-weight:800;color:#1c1917;letter-spacing:-.02em}

.set-section{background:#fff;border:1.5px solid #f5f5f4;border-radius:16px;padding:20px 24px;margin-bottom:16px}
.set-section-title{font-size:14px;font-weight:800;color:#1c1917;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.set-section-desc{font-size:12px;color:#78716c;margin-bottom:16px;line-height:1.5}
.set-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid #f5f5f4;gap:12px}
.set-row:first-child{border-top:none}
.set-row-label{font-size:13px;font-weight:600;color:#1c1917}
.set-row-hint{font-size:11px;color:#a8a29e;margin-top:2px}

/* Toggle switch */
.set-toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.set-toggle input{opacity:0;width:0;height:0}
.set-toggle-slider{position:absolute;inset:0;background:#d6d3d1;border-radius:99px;cursor:pointer;transition:background .25s}
.set-toggle-slider::before{content:'';position:absolute;left:3px;top:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.set-toggle input:checked+.set-toggle-slider{background:linear-gradient(135deg,#ea580c,#f97316)}
.set-toggle input:checked+.set-toggle-slider::before{transform:translateX(20px)}

/* Select */
.set-select{padding:8px 12px;border:1.5px solid #e7e5e4;border-radius:8px;font-size:13px;font-family:inherit;color:#1c1917;background:#fff;cursor:pointer}
.set-select:focus{border-color:#ea580c;outline:none}

.set-save{margin-top:8px}
.set-saved{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 16px;font-size:12px;color:#166534;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
</style>

<div class="set-wrap">
    <div class="set-header">
        <div class="set-hicon">
            <svg viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="set-htitle">Ustawienia</div>
    </div>

    <?php if(!empty($saved)):?>
    <div class="set-saved">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
        Ustawienia zostaly zapisane
    </div>
    <?php endif;?>

    <form method="post">
        <input type="hidden" name="_token" value="<?=h($_SESSION['csrf']??'')?>">

        <div class="set-section">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Formularze
            </div>
            <div class="set-section-desc">Zarzadzaj widocznoscia sekcji formularzy i mozliwoscia wysylania wnioskow.</div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Pokaz "Formularze" w menu</div>
                    <div class="set-row-hint">Pracownicy widza opcje Formularze w sidebarze.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="forms_visible" value="1" <?=$formsVisible?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Wysylanie formularzy do akceptacji</div>
                    <div class="set-row-hint">Pracownicy moga wyslac wypelniony formularz do administratora do akceptacji.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="form_submissions_enabled" value="1" <?=$formSubEnabled?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">E-mail do administratora o nowym wniosku</div>
                    <div class="set-row-hint">Przypisany administrator otrzyma e-mail gdy pracownik zlozy nowy wniosek.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="email_notify_admin" value="1" <?=$emailAdminEnabled?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">E-mail do Kadr o zaakceptowanym wniosku</div>
                    <div class="set-row-hint">Pracownicy z rola Kadry otrzymaja e-mail gdy wniosek zostanie zaakceptowany.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="email_notify_kadry" value="1" <?=$emailKadryEnabled?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="set-section">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                Dashboard
            </div>
            <div class="set-section-desc">Zarzadzaj widocznoscia strony Dashboard dla pracownikow.</div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Pokaz Dashboard w menu</div>
                    <div class="set-row-hint">Pracownicy widza strone startowa Dashboard z kafelkami.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="dashboard_visible" value="1" <?=$dashVisibleSetting?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
        </div>



        <div class="set-section" style="margin-top:20px">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Przypisanie osob akceptujacych
            </div>
            <div class="set-section-desc">Dla kazdego pracownika wybierz administratorow, ktorzy akceptuja jego wnioski. Mozna wybrac wielu.</div>
            <?php if(!empty($allForApproval)):?>
            <div style="max-height:400px;overflow-y:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="border-bottom:2px solid #fed7aa">
                        <th style="padding:8px 10px;text-align:left;font-size:11px;color:#78716c;font-weight:700">Pracownik / Admin</th>
                        <th style="padding:8px 10px;text-align:left;font-size:11px;color:#78716c;font-weight:700">Akceptuja</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($allForApproval as $emp):
                    $eApprovers = $approverMap[$emp['id']] ?? [];
                    $roleLabel = $emp['role']==='admin'?'Admin':($emp['role']==='kadry'?'Kadry':'');
                ?>
                    <tr style="border-bottom:1px solid #f5f5f4">
                        <td style="padding:8px 10px;font-weight:600;color:#1c1917;white-space:nowrap"><?=h($emp['full_name'])?><?=$roleLabel?' <span style="font-size:9px;padding:1px 5px;border-radius:4px;background:#f5f5f4;color:#78716c;font-weight:700">'.$roleLabel.'</span>':''?><div style="font-size:10px;color:#a8a29e;font-weight:400"><?=h($emp['email'])?></div></td>
                        <td style="padding:8px 10px">
                            <div style="display:flex;flex-wrap:wrap;gap:4px">
                            <?php foreach($admins as $adm):
                                if($adm['id']===$emp['id']) continue; // Can't approve yourself
                                $checked = in_array($adm['id'], $eApprovers);
                            ?>
                            <label style="display:flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;cursor:pointer;font-size:11px;background:<?=$checked?'#fff7ed':'#fafaf9'?>;border:1px solid <?=$checked?'#fed7aa':'#f5f5f4'?>">
                                <input type="checkbox" name="approvers[<?=$emp['id']?>][]" value="<?=$adm['id']?>" <?=$checked?'checked':''?> style="width:14px;height:14px;accent-color:#ea580c">
                                <?=h($adm['full_name'])?>
                            </label>
                            <?php endforeach;?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            </div>
            <?php else:?>
            <p style="font-size:12px;color:#a8a29e">Brak uzytkownikow.</p>
            <?php endif;?>
        </div>

        <div class="set-section">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Widocznosc harmonogramu
            </div>
            <div class="set-section-desc">Okresl, czy pracownicy moga widziec harmonogram innych pracownikow.</div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Tryb widocznosci</div>
                    <div class="set-row-hint">Ustawienie globalne dla wszystkich pracownikow.</div>
                </div>
                <select name="employee_view_mode" class="set-select">
                    <option value="own" <?=$empViewMode==='own'?'selected':''?>>Tylko wlasny grafik</option>
                    <option value="all" <?=$empViewMode==='all'?'selected':''?>>Wszyscy widza calosc</option>
                    <option value="individual" <?=$empViewMode==='individual'?'selected':''?>>Indywidualnie</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary set-save">Zapisz ustawienia</button>
    </form>

    <!-- ── ANNOUNCEMENTS MANAGEMENT ── -->
    <div class="set-section" style="margin-top:20px">
        <div class="set-section-title">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            Ogloszenia (Dashboard)
        </div>
        <div class="set-section-desc">Dodawaj ogloszenia widoczne na Dashboard dla wszystkich pracownikow.</div>

        <!-- Add announcement form -->
        <form method="post" style="margin-bottom:16px">
            <input type="hidden" name="_token" value="<?=h($_SESSION['csrf']??'')?>">
            <input type="hidden" name="ann_action" value="add">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                <input type="text" name="ann_title" placeholder="Tytul ogloszenia" required style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid #e7e5e4;border-radius:8px;font-size:13px;font-family:inherit">
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#ea580c;cursor:pointer;padding:0 8px;border:1.5px solid #fed7aa;border-radius:8px;background:#fff7ed">
                    <input type="checkbox" name="ann_important" value="1" style="accent-color:#ea580c"> Wazne
                </label>
                <label style="display:flex;align-items:center;gap:4px;font-size:12px;font-weight:600;color:#2563eb;cursor:pointer;padding:0 8px;border:1.5px solid #bfdbfe;border-radius:8px;background:#eff6ff">
                    <input type="checkbox" name="ann_send_email" value="1" style="accent-color:#2563eb"> E-mail
                </label>
            </div>
            <textarea name="ann_body" placeholder="Tresc ogloszenia..." required rows="3" style="width:100%;padding:8px 12px;border:1.5px solid #e7e5e4;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;box-sizing:border-box;margin-bottom:8px"></textarea>
            <button type="submit" class="btn btn-primary" style="font-size:12px;padding:8px 16px">Dodaj ogloszenie</button>
        </form>

        <?php if(!empty($msg_ann)):?><div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:8px 12px;font-size:12px;color:#166534;margin-bottom:12px"><?=h($msg_ann)?></div><?php endif;?>

        <!-- Existing announcements list -->
        <?php if(!empty($annList)):?>
        <div style="max-height:300px;overflow-y:auto">
        <?php foreach($annList as $ann):?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f5f5f4;font-size:12px">
            <div style="flex:1;min-width:0">
                <div style="font-weight:700;color:#1c1917;display:flex;align-items:center;gap:6px">
                    <?=$ann['is_important']?'<span style="font-size:8px;padding:1px 5px;border-radius:3px;background:#ea580c;color:#fff;font-weight:800">WAŻNE</span>':''?>
                    <?=h($ann['title'])?>
                </div>
                <div style="font-size:10px;color:#a8a29e"><?=date('d.m.Y',strtotime($ann['created_at']))?> · <?=h($ann['author']??'')?></div>
            </div>
            <form method="post" style="display:inline" onsubmit="return confirm('Usunac ogloszenie?')">
                <input type="hidden" name="_token" value="<?=h($_SESSION['csrf']??'')?>">
                <input type="hidden" name="ann_action" value="delete">
                <input type="hidden" name="ann_id" value="<?=$ann['id']?>">
                <button class="btn btn-sm btn-danger" title="Usun" style="padding:4px 8px">🗑</button>
            </form>
        </div>
        <?php endforeach;?>
        </div>
        <?php else:?>
        <p style="font-size:12px;color:#a8a29e">Brak ogloszen.</p>
        <?php endif;?>
    </div>


</div>

<?php layout_end(); ?>
