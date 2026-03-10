<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

function layout_start(string $title = ''): void {
    $user = current_user();
    $pt = $title ? $title . ' — ' . APP_NAME : APP_NAME;
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($pt) ?> — ePracownik SCK</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($user): ?>
<div class="app" id="app">
<nav class="sidebar" id="sidebar">
    <!-- Ambient glow -->
    <div class="sb-glow"></div>

    <div class="sb-brand">
        <div class="sb-brand-inner">
            <img src="assets/logo_white.png" alt="Strzegomskie Centrum Kultury" class="sb-logo-img">
        </div>
        <div class="sb-shimmer"></div>
    </div>
    <div class="sb-nav">
        <div class="sb-section"><span>Menu</span></div>
        <?php $dashVisible = get_setting('dashboard_visible', '1') === '1'; ?>
        <?php if ($dashVisible || is_admin()): ?>
        <a href="dashboard.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='dashboard.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg></span>
            <span class="nav-label">Dashboard</span>
        </a>
        <?php endif; ?>
        <a href="schedule.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='schedule.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg></span>
            <span class="nav-label"><?= is_admin()?'Harmonogram':'Mój grafik' ?></span>
        </a>
        <?php if (is_admin()): ?>
        <a href="employees.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='employees.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="nav-label">Pracownicy</span>
        </a>
        <?php endif; ?>
        <?php
            $lbPage = basename($_SERVER['SCRIPT_NAME'])==='leave_balances.php';
            $lbView = $_GET['view'] ?? '';
            $lbActive = $lbPage;
        ?>
        <?php if (is_admin()): ?>
        <div class="nav-group <?=$lbActive?'nav-group-open':''?>">
            <button class="nav-btn nav-group-btn <?=$lbActive?'act':''?>" onclick="this.parentElement.classList.toggle('nav-group-open')">
                <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/></svg></span>
                <span class="nav-label">Urlopy i nadgodziny</span>
                <svg class="nav-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="nav-sub">
                <a href="leave_balances.php" class="nav-sub-btn <?=$lbPage&&$lbView!=='my'?'act':''?>">
                    <span class="nav-sub-dot"></span><span class="nav-label">Zarządzanie</span>
                </a>
                <a href="leave_balances.php?view=my" class="nav-sub-btn <?=$lbPage&&$lbView==='my'?'act':''?>">
                    <span class="nav-sub-dot"></span><span class="nav-label">Moje dane</span>
                </a>
            </div>
        </div>
        <?php else: ?>
        <a href="leave_balances.php" class="nav-btn <?=$lbActive?'act':''?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/></svg></span>
            <span class="nav-label">Urlopy i nadgodziny</span>
        </a>
        <?php endif; ?>
        <?php $nUnread = get_unread_count($user['id']); ?>
        <a href="notifications.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='notifications.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></span>
            <span class="nav-label">Powiadomienia</span>
            <?php if($nUnread):?><span class="nav-badge"><?=$nUnread?></span><?php endif;?>
        </a>
        <?php $formsVisible = get_setting('forms_visible', '1') === '1'; ?>
        <?php if ($formsVisible || is_admin()): ?>
        <?php $fmActive = in_array(basename($_SERVER['SCRIPT_NAME']), ['forms.php','form_wifi.php','form_overtime.php','form_leave.php','my_requests.php']); ?>
        <a href="forms.php" class="nav-btn <?=$fmActive?'act':''?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
            <span class="nav-label">Formularze</span>
        </a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
        <?php
            $pendingCount = 0;
            $stmtPc = get_db()->prepare("SELECT COUNT(*) FROM form_requests fr JOIN form_approvers fa ON fa.employee_id=fr.user_id AND fa.approver_id=? WHERE fr.status='pending'");
            $stmtPc->execute([$user['id']]);
            $pendingCount = (int)$stmtPc->fetchColumn();
        ?>
        <a href="requests.php" class="nav-btn <?=basename($_SERVER['SCRIPT_NAME'])==='requests.php'?'act':''?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M9 14l2 2 4-4"/></svg></span>
            <span class="nav-label">Wnioski</span>
            <?php if($pendingCount):?><span class="nav-badge"><?=$pendingCount?></span><?php endif;?>
        </a>
        <a href="cinema.php" class="nav-btn <?=basename($_SERVER['SCRIPT_NAME'])==='cinema.php'?'act':''?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 3v18"/><path d="M3 7.5h4"/><path d="M3 12h4"/><path d="M3 16.5h4"/><path d="M17 3v18"/><path d="M17 7.5h4"/><path d="M17 12h4"/><path d="M17 16.5h4"/></svg></span>
            <span class="nav-label">Rozliczenie kina</span>
        </a>
        <?php endif; ?>

        <div class="sb-section"><span>Konto</span></div>
        <?php if (is_super_admin()): ?>
        <a href="settings.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='settings.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg></span>
            <span class="nav-label">Ustawienia</span>
        </a>
        <?php endif; ?>
        <a href="profile.php" class="nav-btn <?= basename($_SERVER['SCRIPT_NAME'])==='profile.php'?'act':'' ?>">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M18.63 13a7.72 7.72 0 0 0 0-2l2.1-1.63a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.6 7.6 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 13.12 2h-4a.5.5 0 0 0-.5.42L8.24 5.07a7.6 7.6 0 0 0-1.73 1l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64L3.63 11a7.72 7.72 0 0 0 0 2l-2.1 1.63a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.22l2.49-1a7.6 7.6 0 0 0 1.73 1l.38 2.65a.5.5 0 0 0 .5.42h4a.5.5 0 0 0 .5-.42l.38-2.65a7.6 7.6 0 0 0 1.73-1l2.49 1a.5.5 0 0 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64z"/></svg></span>
            <span class="nav-label">Moje konto</span>
        </a>
    </div>
    <div class="sb-footer">
        <div class="sb-card">
            <div class="sb-user">
                <div class="sb-avatar <?=$user['role']==='admin'?'sb-avatar-admin':'sb-avatar-emp'?>"><?=mb_substr($user['full_name'],0,1)?></div>
                <div class="sb-user-info">
                    <div class="sb-user-name"><?= h($user['full_name']) ?></div>
                    <div class="sb-user-role">
                        <?php if($user['role']==='admin'):?><svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="color:#c4b5fd;margin-right:2px;vertical-align:-1px"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg><?php endif;?>
                        <?= $user['role']==='admin'?'Administrator':($user['role']==='kadry'?'Kadry':'Pracownik') ?> · <?= h($user['department']) ?>
                    </div>
                </div>
            </div>
            <a href="logout.php" class="sb-logout">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                <span class="nav-label">Wyloguj</span>
            </a>
        </div>
    </div>
    <!-- Collapse toggle -->
    <div class="sb-collapse-wrap">
        <button class="sb-collapse-btn" id="sbToggle" onclick="toggleSidebar()" title="Zwiń menu">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="collapse-icon"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
            <span class="nav-label">Zwiń menu</span>
        </button>
    </div>
</nav>
<main class="content">
<?php endif; ?>
<?php }

function layout_end(): void { if (current_user()): ?>
</main></div>
<!-- Idle logout timer -->
<div class="idle-timer" id="idleTimer">
    <svg class="idle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span id="idleText">10:00</span>
</div>
<style>
.idle-timer{position:fixed;top:12px;right:16px;z-index:100;display:flex;align-items:center;gap:6px;padding:6px 14px;
  border-radius:99px;background:rgba(250,250,249,.85);backdrop-filter:blur(8px);border:1px solid #e7e5e4;
  font-size:11px;font-weight:600;color:#78716c;font-family:inherit;transition:all .4s;cursor:default;
  box-shadow:0 2px 8px rgba(0,0,0,.04);font-variant-numeric:tabular-nums}
.idle-timer.idle-warn{background:rgba(255,247,237,.95);border-color:#fed7aa;color:#ea580c}
.idle-timer.idle-warn .idle-icon{stroke:#ea580c}
.idle-timer.idle-danger{background:rgba(254,242,242,.95);border-color:#fecaca;color:#dc2626;animation:idlePulse 1s ease-in-out infinite}
.idle-timer.idle-danger .idle-icon{stroke:#dc2626}
@keyframes idlePulse{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.2)}50%{box-shadow:0 0 12px 2px rgba(220,38,38,.15)}}
.idle-icon{stroke:#a8a29e;transition:stroke .4s}
</style>
<script>
function toggleSidebar(){
    const app=document.getElementById('app');
    const c=app.classList.toggle('collapsed');
    localStorage.setItem('sb_collapsed',c?'1':'0');
}
(function(){
    if(localStorage.getItem('sb_collapsed')==='1'){
        document.getElementById('app').classList.add('collapsed');
    }
})();

/* ── IDLE AUTO-LOGOUT ── */
(function(){
    var IDLE_LIMIT=600; // 10 minutes in seconds
    var remaining=IDLE_LIMIT;
    var timerEl=document.getElementById('idleTimer');
    var textEl=document.getElementById('idleText');

    function resetIdle(){remaining=IDLE_LIMIT;updateDisplay();}

    function updateDisplay(){
        var m=Math.floor(remaining/60);
        var s=remaining%60;
        textEl.textContent=m+':'+(s<10?'0':'')+s;
        timerEl.classList.remove('idle-warn','idle-danger');
        if(remaining<=60) timerEl.classList.add('idle-danger');
        else if(remaining<=180) timerEl.classList.add('idle-warn');
    }

    function tick(){
        remaining--;
        if(remaining<=0){
            window.location.href='logout.php';
            return;
        }
        updateDisplay();
    }

    // Reset on any user activity
    ['mousemove','mousedown','keypress','scroll','touchstart','click'].forEach(function(evt){
        document.addEventListener(evt,resetIdle,{passive:true});
    });

    setInterval(tick,1000);
    updateDisplay();
})();
</script>
<?php endif; ?>
</body></html>
<?php } ?>
