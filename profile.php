<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
ensure_users_profile_columns();
$force = !empty($_GET['force']) || $user['must_change_password'];
$msg=''; $mt='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'password';

    if ($action === 'job_title') {
        $jobTitle = trim($_POST['job_title'] ?? '');
        if (mb_strlen($jobTitle) > 191) {
            $msg = 'Stanowisko służbowe może mieć maksymalnie 191 znaków';
            $mt = 'danger';
        } else {
            get_db()->prepare('UPDATE users SET job_title=? WHERE id=?')->execute([$jobTitle, $user['id']]);
            $msg = 'Zapisano stanowisko służbowe';
            $mt = 'success';
        }
    } else {
        $old=$_POST['old_password']??''; $new=$_POST['new_password']??''; $rep=$_POST['repeat_password']??'';
        if (!$force && !password_verify($old,$user['password'])) { $msg='Obecne hasło jest nieprawidłowe'; $mt='danger'; }
        elseif (strlen($new)<6) { $msg='Nowe hasło musi mieć min. 6 znaków'; $mt='danger'; }
        elseif ($new!==$rep) { $msg='Hasła nie są identyczne'; $mt='danger'; }
        else {
            get_db()->prepare('UPDATE users SET password=?, must_change_password=0 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);
            $msg='Hasło zostało zmienione'; $mt='success'; $force=false;
        }
    }

    $stmt=get_db()->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$user['id']]);
    $user=$stmt->fetch();
}
layout_start('Moje konto');
?>

<?php if($force):?><div class="alert alert-warn">⚠️ Musisz zmienić hasło przed dalszym korzystaniem z systemu.</div><?php endif;?>
<?php if($msg):?><div class="alert alert-<?=$mt?>"><?=h($msg)?></div><?php endif;?>

<div class="page-head"><h2>Moje konto</h2></div>

<div class="card" style="max-width:600px">
    <div class="profile-row">
        <div class="avatar"><?=mb_strtoupper(mb_substr($user['full_name'],0,1))?></div>
        <div><h3><?=h($user['full_name'])?></h3><div class="text-muted"><?=h($user['email'])?></div></div>
        <span class="badge <?=$user['role']==='admin'?'badge-admin':'badge-emp'?>"><?=$user['role']==='admin'?'Administrator HR':'Pracownik'?></span>
    </div>
    <div class="g3">
        <div class="stat"><div class="stat-l">Dział</div><div class="stat-v"><?=h($user['department'])?></div></div>
        <div class="stat"><div class="stat-l">Etat</div><div class="stat-v"><?=$user['employment_fraction']?></div></div>
        <div class="stat"><div class="stat-l">Konto</div><div class="stat-v"><?=$user['active']?'Aktywne':'Zablokowane'?></div></div>
    </div>
</div>

<div class="card" style="max-width:600px;margin-top:20px">
    <h3>🪪 Stanowisko służbowe</h3>
    <form method="post">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="job_title">
        <label class="lbl">Stanowisko</label>
        <input type="text" name="job_title" class="input" maxlength="191" placeholder="np. Specjalista ds. kultury" value="<?=h($user['job_title'] ?? '')?>">
        <div style="margin-top:16px"><button type="submit" class="btn btn-primary">✓ Zapisz stanowisko</button></div>
    </form>
</div>

<div class="card" style="max-width:600px;margin-top:20px">
    <h3><?=$force?'🔒 Ustaw nowe hasło':'🔒 Zmiana hasła'?></h3>
    <form method="post">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="password">
        <?php if(!$force):?><label class="lbl">Obecne hasło</label><input type="password" name="old_password" class="input" required><hr><?php endif;?>
        <label class="lbl">Nowe hasło (min. 6 znaków)</label><input type="password" name="new_password" class="input" required minlength="6">
        <label class="lbl">Powtórz nowe hasło</label><input type="password" name="repeat_password" class="input" required>
        <div style="margin-top:16px"><button type="submit" class="btn btn-primary">✓ <?=$force?'Ustaw hasło':'Zmień hasło'?></button></div>
    </form>
</div>

<?php layout_end(); ?>
