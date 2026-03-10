<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_admin();
$msg=''; $mt='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';
    $db  = get_db();

    if ($act==='create') {
        $email=trim($_POST['email']??''); $name=trim($_POST['full_name']??'');
        $dept=trim($_POST['department']??'SCK'); $frac=(float)($_POST['employment_fraction']??1);
        $role=$_POST['role']??'employee';
        if(!in_array($role,['admin','kadry','employee']))$role='employee';
        $pass=trim($_POST['init_password']??'sck2025');
        if (!$email||!$name) { $msg='Wypełnij imię i e-mail'; $mt='danger'; }
        else {
            $hash=password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare('INSERT INTO users (email,password,full_name,department,employment_fraction,role,active,must_change_password) VALUES (?,?,?,?,?,?,1,1)')
               ->execute([$email,$hash,$name,$dept,$frac,$role]);
            $msg="Utworzono konto: $name (hasło: $pass)"; $mt='success';
        }
    }
    if ($act==='toggle') {
        $id=(int)($_POST['uid']??0);
        $db->prepare('UPDATE users SET active = NOT active WHERE id=? AND id!=?')->execute([$id,$user['id']]);
        $msg='Status zmieniony'; $mt='success';
    }
    if ($act==='reset_password') {
        $id=(int)($_POST['uid']??0);
        $db->prepare('UPDATE users SET password=?, must_change_password=1 WHERE id=?')->execute([password_hash('sck2025',PASSWORD_DEFAULT),$id]);
        $msg='Hasło zresetowane (nowe: sck2025)'; $mt='success';
    }
    if ($act==='set_role') {
        $id=(int)($_POST['uid']??0);
        $newRole = trim($_POST['new_role'] ?? '');
        if (!$newRole) $newRole = 'employee';
        if ($id == $user['id']) { $msg='Nie możesz zmienić własnej roli'; $mt='danger'; }
        elseif (!in_array($newRole, ['admin','kadry','employee'], true)) { $msg='Nieprawidłowa rola: '.htmlspecialchars($newRole); $mt='danger'; }
        else {
            // Ensure role column supports 'kadry' value
            try { $db->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'employee'"); } catch(Exception $ex) {}
            $db->prepare('UPDATE users SET role=? WHERE id=?')->execute([$newRole, $id]);
            $roleLabels = ['admin'=>'Administrator','kadry'=>'Kadry','employee'=>'Pracownik'];
            $msg='Rola zmieniona na: '.$roleLabels[$newRole]; $mt='success';
        }
    }
    if ($act==='toggle_view') {
        $id=(int)($_POST['uid']??0);
        $db->prepare('UPDATE users SET can_view_all = NOT can_view_all WHERE id=?')->execute([$id]);
        $msg='Uprawnienia widoku zmienione'; $mt='success';
    }
    if ($act==='global_view') {
        $mode = $_POST['mode'] ?? 'own';
        $allowed = ['own','individual'];
        if (in_array($mode, $allowed)) {
            set_setting('employee_view_mode', $mode);
            $msg = $mode === 'own'
                ? 'Globalne ograniczenie: pracownicy widzą tylko swój grafik'
                : 'Tryb indywidualny: widoczność zależy od ustawień każdego pracownika';
            $mt = 'success';
        }
    }
    if ($act==='delete_user') {
        $id=(int)($_POST['uid']??0);
        if ($id === $user['id']) { $msg='Nie możesz usunąć własnego konta'; $mt='danger'; }
        else {
            $stmt = $db->prepare('SELECT full_name FROM users WHERE id=?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
                $msg='Usunięto konto: '.$row['full_name'].' (wraz z wpisami harmonogramu)'; $mt='success';
            }
        }
    }
}

$all = get_all_users();
$globalViewMode = get_setting('employee_view_mode', 'own');
layout_start('Pracownicy');
?>

<div class="page-head"><h2>Pracownicy</h2>
<button class="btn btn-primary" onclick="document.getElementById('am').style.display='flex'">+ Dodaj pracownika</button></div>

<?php if($msg):?><div class="alert alert-<?=$mt?>"><?=h($msg)?></div><?php endif;?>

<!-- Global view settings -->
<div class="card settings-card">
    <div class="settings-head">
        <span class="settings-icon">👁</span>
        <div>
            <strong>Widoczność harmonogramu dla pracowników</strong>
            <div class="settings-desc">Zdecyduj czy pracownicy mogą widzieć grafik innych osób</div>
        </div>
    </div>
    <div class="settings-opts">
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="global_view"><input type="hidden" name="mode" value="own">
            <button class="settings-btn <?=$globalViewMode==='own'?'settings-btn-act':''?>">
                <span class="settings-btn-icon">🔒</span>
                <span class="settings-btn-label">Tylko własny grafik</span>
                <span class="settings-btn-desc">Każdy pracownik widzi wyłącznie swój harmonogram</span>
            </button>
        </form>
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="global_view"><input type="hidden" name="mode" value="individual">
            <button class="settings-btn <?=$globalViewMode==='individual'?'settings-btn-act':''?>">
                <span class="settings-btn-icon">👁</span>
                <span class="settings-btn-label">Indywidualne uprawnienia</span>
                <span class="settings-btn-desc">Włącz podgląd wybranym pracownikom (kolumna „Widok" w tabeli)</span>
            </button>
        </form>
    </div>
</div>

<div class="card" style="overflow-x:auto">
<table class="dtable">
<thead><tr><th>Imię i nazwisko</th><th>Dział</th><th>Etat</th><th>Rola</th><?php if($globalViewMode==='individual'):?><th>Widok</th><?php endif;?><th>Konto</th><th>E-mail</th><th style="min-width:120px">Akcje</th></tr></thead>
<tbody>
<?php foreach($all as $e): ?>
<tr>
    <td><strong><?=h($e['full_name'])?></strong></td>
    <td><?=h($e['department'])?></td>
    <td><?=$e['employment_fraction']?></td>
    <td>
        <?php if($e['id']!==$user['id']): ?>
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="set_role"><input type="hidden" name="uid" value="<?=$e['id']?>">
            <select name="new_role" onchange="this.form.submit()" class="role-select role-<?=$e['role']?>">
                <option value="employee" <?=$e['role']==='employee'?'selected':''?>>Pracownik</option>
                <option value="kadry" <?=$e['role']==='kadry'?'selected':''?>>Kadry</option>
                <option value="admin" <?=$e['role']==='admin'?'selected':''?>>Admin</option>
            </select>
        </form>
        <?php else: ?>
        <span class="badge badge-admin"><?=$e['role']==='admin'?'Admin':($e['role']==='kadry'?'Kadry':'Pracownik')?></span>
        <?php endif; ?>
    </td>
    <?php if($globalViewMode==='individual'): ?>
    <td>
        <?php if($e['role']==='employee'): ?>
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="toggle_view"><input type="hidden" name="uid" value="<?=$e['id']?>">
            <button class="badge-btn <?=$e['can_view_all']?'badge-view-on':'badge-view-off'?>"><?=$e['can_view_all']?'Pełny':'Własny'?></button>
        </form>
        <?php else: ?>
        <span class="badge badge-ok">Pełny</span>
        <?php endif; ?>
    </td>
    <?php endif; ?>
    <td><span class="badge <?=$e['active']?'badge-ok':'badge-danger'?>"><?=$e['active']?'Aktywne':'Zablok.'?></span></td>
    <td style="font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;color:#78716c"><?=h($e['email'])?></td>
    <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?=csrf_field()?><input type="hidden" name="action" value="toggle"><input type="hidden" name="uid" value="<?=$e['id']?>">
        <button class="btn btn-sm btn-ghost" title="<?=$e['active']?'Zablokuj konto':'Aktywuj konto'?>"><?=$e['active']?'🔒':'✅'?></button></form>
        <form method="post" style="display:inline" onsubmit="return confirm('Zresetować hasło?')"><?=csrf_field()?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="uid" value="<?=$e['id']?>">
        <button class="btn btn-sm btn-ghost" title="Reset hasła">🔑</button></form>
        <?php if($e['id']!==$user['id']): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Usunąć konto <?=h($e['full_name'])?>?')">
        <?=csrf_field()?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="uid" value="<?=$e['id']?>">
        <button class="btn btn-sm btn-danger" title="Usuń konto">🗑</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>

<!-- ADD MODAL -->
<div class="overlay" id="am" style="display:none" onclick="if(event.target===this)this.style.display='none'">
<div class="modal" onclick="event.stopPropagation()">
<div class="mh"><h3>Nowy pracownik</h3><button class="mc" onclick="document.getElementById('am').style.display='none'">×</button></div>
<form method="post"><div class="mb"><?=csrf_field()?><input type="hidden" name="action" value="create">
    <label class="lbl">Imię i nazwisko</label><input name="full_name" class="input" required>
    <label class="lbl">E-mail służbowy</label><input name="email" type="email" class="input" required>
    <label class="lbl">Hasło początkowe</label><input name="init_password" class="input" value="sck2025">
    <small class="text-muted">Pracownik zmieni hasło przy 1. logowaniu</small>
    <div class="g2"><div><label class="lbl">Dział</label><input name="department" class="input" value="SCK"></div>
    <div><label class="lbl">Etat</label><select name="employment_fraction" class="input"><option value="1.00">1.0 (160h)</option><option value="0.75">0.75 (120h)</option><option value="0.50">0.5 (80h)</option><option value="0.25">0.25 (40h)</option></select></div></div>
    <label class="lbl">Rola</label><select name="role" class="input"><option value="employee">Pracownik</option><option value="admin">Administrator HR</option></select>
</div><div class="mf"><button type="submit" class="btn btn-primary">✓ Utwórz konto</button><button type="button" class="btn btn-ghost" onclick="document.getElementById('am').style.display='none'">Anuluj</button></div></form>
</div></div>

<?php layout_end(); ?>
