<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$dashVisible = get_setting('dashboard_visible', '1') === '1';
if (!$dashVisible && !is_admin()) { header('Location: schedule.php'); exit; }

$db = get_db();
$currentYear = (int)date('Y');
$currentMonth = (int)date('m');

// Leave data
$myLeave = calc_leave_remaining($user['id'], $currentYear);

// Overtime
$stOt = $db->prepare('SELECT overtime_hours FROM leave_balances WHERE user_id=? AND year=?');
$stOt->execute([$user['id'], $currentYear]);
$otRow = $stOt->fetch();
$myOt = $otRow ? (float)$otRow['overtime_hours'] : 0;

// My requests
$stReq = $db->prepare("SELECT status, COUNT(*) as cnt FROM form_requests WHERE user_id=? GROUP BY status");
$stReq->execute([$user['id']]);
$reqStats = ['pending'=>0,'approved'=>0,'rejected'=>0];
foreach ($stReq->fetchAll() as $r) $reqStats[$r['status']] = (int)$r['cnt'];

$pendingApprovals = 0;
$pendingApprovalsPeople = [];
if (is_admin()) {
    $stPendingApprovals = $db->prepare("
        SELECT fr.id, u.full_name
        FROM form_requests fr
        JOIN users u ON u.id = fr.user_id
        JOIN form_approvers fa ON fa.employee_id = fr.user_id AND fa.approver_id = ?
        WHERE fr.status = 'pending'
        ORDER BY fr.created_at DESC
    " );
    $stPendingApprovals->execute([$user['id']]);
    $pendingRows = $stPendingApprovals->fetchAll();
    $pendingApprovals = count($pendingRows);
    $pendingApprovalsPeople = array_values(array_unique(array_map(static function($r){ return (string)$r['full_name']; }, $pendingRows)));
}

// My duties this month
$monthStart = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$dim = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$monthEnd = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $dim);
$stDyz = $db->prepare("SELECT entry_date, shift_start, shift_end FROM schedule_entries WHERE user_id=? AND shift_type='dyzur' AND entry_date BETWEEN ? AND ? ORDER BY entry_date");
$stDyz->execute([$user['id'], $monthStart, $monthEnd]);
$myDuties = $stDyz->fetchAll();

// Announcements
$announcements = $db->query("SELECT a.*, u.full_name AS author FROM announcements a LEFT JOIN users u ON u.id=a.created_by WHERE a.active=1 ORDER BY a.is_important DESC, a.created_at DESC LIMIT 10")->fetchAll();

$monthNames = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];

layout_start('Dashboard');
?>

<style>
@keyframes dbFadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@keyframes dbPulse{0%,100%{box-shadow:0 0 0 0 rgba(234,88,12,.3)}50%{box-shadow:0 0 20px 4px rgba(234,88,12,.15)}}
@keyframes dbRingIn{from{stroke-dashoffset:251.3}to{stroke-dashoffset:var(--ring-to)}}
@keyframes dbShine{0%{left:-100%}100%{left:200%}}
@keyframes dbImpPulse{0%,100%{box-shadow:0 0 0 0 rgba(234,88,12,.2),inset 0 0 0 1px rgba(234,88,12,.3)}50%{box-shadow:0 0 16px 4px rgba(234,88,12,.12),inset 0 0 0 1px rgba(234,88,12,.5)}}

.db-wrap{max-width:1000px;margin:0 auto;padding:20px 24px 40px}
.db-hello{font-size:28px;font-weight:900;color:#1c1917;letter-spacing:-.03em;margin-bottom:4px}
.db-hello span{background:linear-gradient(135deg,#ea580c,#f97316,#fb923c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.db-sub{font-size:13px;color:#a8a29e;margin-bottom:24px}
.db-approvals-alert{margin:-8px 0 18px;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1.5px solid #fdba74;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.db-approvals-alert-title{font-size:13px;font-weight:800;color:#9a3412;margin-bottom:4px}
.db-approvals-alert-text{font-size:12px;color:#7c2d12;line-height:1.45}
.db-approvals-alert-link{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;background:#ea580c;color:#fff;text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap}
.db-approvals-alert-link:hover{background:#c2410c}

.db-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:700px){.db-grid{grid-template-columns:1fr}}

/* ── TILE BASE ── */
.db-tile{background:#fff;border:1.5px solid #f5f5f4;border-radius:20px;padding:24px;overflow:hidden;position:relative;cursor:pointer;
  transition:transform .25s,box-shadow .25s,border-color .25s;opacity:0;animation:dbFadeUp .5s ease-out forwards}
.db-tile:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.08);border-color:#fed7aa}
.db-tile-head{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.db-tile-icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.db-tile-icon svg{width:22px;height:22px;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.db-tile-title{font-size:15px;font-weight:800;color:#1c1917;letter-spacing:-.01em}
.db-tile-subtitle{font-size:11px;color:#a8a29e;font-weight:500}

/* ── LEAVE TILE ── */
.db-leave-icon{background:linear-gradient(135deg,#ea580c,#f97316)}
.db-leave-icon svg{stroke:#fff}
.db-rings{display:flex;gap:20px;justify-content:center;margin:8px 0}
.db-ring{text-align:center}
.db-ring svg{width:90px;height:90px;transform:rotate(-90deg)}
.db-ring-bg{fill:none;stroke:#f5f5f4;stroke-width:8}
.db-ring-fg{fill:none;stroke-width:8;stroke-linecap:round;stroke-dasharray:251.3;stroke-dashoffset:251.3;animation:dbRingIn 1.5s .5s ease-out forwards}
.db-ring-center{font-size:22px;font-weight:900;color:#1c1917;margin-top:-62px;position:relative;line-height:1}
.db-ring-unit{font-size:9px;color:#a8a29e;font-weight:600;text-transform:uppercase;letter-spacing:.05em;display:block;margin-top:1px}
.db-ring-label{font-size:10px;color:#78716c;font-weight:600;margin-top:18px}

/* ── ANNOUNCEMENTS TILE ── */
.db-ann-icon{background:linear-gradient(135deg,#f97316,#fb923c)}
.db-ann-icon svg{stroke:#fff}
.db-ann-list{display:flex;flex-direction:column;gap:8px;max-height:200px;overflow-y:auto}
.db-ann-item{padding:10px 14px;border-radius:12px;background:#fafaf9;border:1px solid #f5f5f4;transition:background .15s}
.db-ann-item:hover{background:#fff7ed}
.db-ann-item-imp{background:#fff7ed;border-color:#ea580c;animation:dbImpPulse 2.5s ease-in-out infinite;position:relative;overflow:hidden}
.db-ann-item-imp::after{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);animation:dbShine 3s ease-in-out infinite}
.db-ann-title{font-size:12px;font-weight:700;color:#1c1917;display:flex;align-items:center;gap:6px}
.db-ann-badge{font-size:8px;font-weight:800;padding:2px 6px;border-radius:4px;background:#ea580c;color:#fff;text-transform:uppercase;letter-spacing:.04em}
.db-ann-date{font-size:10px;color:#a8a29e;margin-top:2px}
.db-ann-body{font-size:11px;color:#57534e;line-height:1.5;margin-top:4px;display:none}
.db-ann-item.open .db-ann-body{display:block}
.db-ann-empty{text-align:center;padding:16px;color:#a8a29e;font-size:12px}

/* ── REQUESTS TILE ── */
.db-req-icon{background:linear-gradient(135deg,#fb923c,#fdba74)}
.db-req-icon svg{stroke:#fff}
.db-req-stats{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.db-req-stat{text-align:center;padding:12px 16px;border-radius:14px;background:#fafaf9;flex:1;min-width:80px}
.db-req-num{font-size:28px;font-weight:900;line-height:1}
.db-req-label{font-size:10px;font-weight:600;color:#78716c;margin-top:4px;text-transform:uppercase;letter-spacing:.03em}
.db-req-pending .db-req-num{color:#ea580c}
.db-req-approved .db-req-num{color:#16a34a}
.db-req-rejected .db-req-num{color:#dc2626}

/* ── DUTIES TILE ── */
.db-dyz-icon{background:linear-gradient(135deg,#fdba74,#fed7aa)}
.db-dyz-icon svg{stroke:#9a3412}
.db-dyz-list{display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto}
.db-dyz-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:10px;background:#fafaf9;border:1px solid #f5f5f4}
.db-dyz-day{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#fff7ed,#ffedd5);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0}
.db-dyz-day-num{font-size:16px;font-weight:900;color:#ea580c;line-height:1}
.db-dyz-day-name{font-size:8px;font-weight:700;color:#9a3412;text-transform:uppercase}
.db-dyz-info{font-size:12px;color:#57534e}
.db-dyz-empty{text-align:center;padding:16px;color:#a8a29e;font-size:12px}

/* ── LINKS TILE ── */
@keyframes dbLinkFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
@keyframes dbLinkShine{0%{left:-100%}100%{left:200%}}
.db-links-icon{background:linear-gradient(135deg,#2563eb,#3b82f6)}
.db-links-icon svg{stroke:#fff}
.db-links-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:500px){.db-links-grid{grid-template-columns:1fr}}
.db-link-card{display:flex;align-items:center;gap:14px;padding:16px 20px;border-radius:16px;text-decoration:none;color:inherit;position:relative;overflow:hidden;transition:transform .3s,box-shadow .3s;animation:dbLinkFloat 4s ease-in-out infinite}
.db-link-card:hover{transform:translateY(-4px)!important;box-shadow:0 12px 32px rgba(0,0,0,.1)}
.db-link-1{background:linear-gradient(135deg,#1e3a5f,#1e40af);animation-delay:0s}
.db-link-2{background:linear-gradient(135deg,#7c2d12,#c2410c);animation-delay:2s}
.db-link-icon-wrap{width:48px;height:48px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.db-link-icon-wrap svg{stroke:#fff}
.db-link-name{font-size:14px;font-weight:800;color:#fff;flex:1}
.db-link-url{position:absolute;bottom:6px;left:82px;font-size:10px;color:rgba(255,255,255,.4)}
.db-link-arrow{color:rgba(255,255,255,.5);transition:transform .2s,color .2s}
.db-link-card:hover .db-link-arrow{transform:translate(3px,-3px);color:#fff}
.db-link-shine{position:absolute;top:0;left:-100%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.08),transparent);animation:dbLinkShine 5s ease-in-out infinite}
.db-link-1 .db-link-shine{animation-delay:0s}
.db-link-2 .db-link-shine{animation-delay:2.5s}
.db-tile-links{cursor:default}
.db-tile-links:hover{transform:none;box-shadow:none;border-color:#f5f5f4}

.db-footer{text-align:center;margin-top:32px;font-size:11px;color:#d6d3d1;letter-spacing:-.01em}

/* Modal for full announcement */
.db-ann-modal{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:24px}
.db-ann-modal.open{display:flex}
.db-ann-modal-box{background:#fff;border-radius:20px;max-width:500px;width:100%;padding:28px;max-height:80vh;overflow-y:auto}
.db-ann-modal-title{font-size:18px;font-weight:800;color:#1c1917;margin-bottom:4px}
.db-ann-modal-meta{font-size:11px;color:#a8a29e;margin-bottom:16px}
.db-ann-modal-body{font-size:14px;color:#57534e;line-height:1.7;white-space:pre-wrap}
.db-ann-modal-close{margin-top:16px}
</style>

<div class="db-wrap">
    <div class="db-hello">Cześć, <span><?=h(explode(' ',$user['full_name'])[0])?></span> 👋</div>
    <div class="db-sub"><?=$monthNames[$currentMonth]?> <?=$currentYear?> — Twój panel pracownika</div>

    <?php if($pendingApprovals > 0): ?>
    <div class="db-approvals-alert">
        <div>
            <div class="db-approvals-alert-title">⏳ Wnioski do rozpatrzenia</div>
            <div class="db-approvals-alert-text">
                Masz <strong><?=$pendingApprovals?></strong> oczekujących wniosków pracowników do akceptacji.
                <?php if(!empty($pendingApprovalsPeople)): ?>
                    Pracownicy: <?=h(implode(', ', array_slice($pendingApprovalsPeople, 0, 3)))?><?=count($pendingApprovalsPeople)>3?' i inni':''?>.
                <?php endif; ?>
            </div>
        </div>
        <a class="db-approvals-alert-link" href="requests.php">Przejdź do wniosków</a>
    </div>
    <?php endif; ?>

    <div class="db-grid">
        <!-- ── LEAVE & OVERTIME TILE ── -->
        <a href="leave_balances.php<?=is_admin()?'?view=my':''?>" class="db-tile" style="animation-delay:.1s;text-decoration:none;color:inherit">
            <div class="db-tile-head">
                <div class="db-tile-icon db-leave-icon"><svg viewBox="0 0 24 24"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/></svg></div>
                <div><div class="db-tile-title">Urlopy i nadgodziny</div><div class="db-tile-subtitle">Rok <?=$currentYear?></div></div>
            </div>
            <div class="db-rings">
                <?php
                $leaveMax = max(1, $myLeave['total']);
                $leavePct = min(100, ($myLeave['remain'] / $leaveMax) * 100);
                $leaveOff = 251.3 - (251.3 * $leavePct / 100);
                $otMax = max(1, $myOt + 40);
                $otPct = min(100, ($myOt / $otMax) * 100);
                $otOff = 251.3 - (251.3 * $otPct / 100);
                ?>
                <div class="db-ring">
                    <svg viewBox="0 0 100 100">
                        <circle class="db-ring-bg" cx="50" cy="50" r="40"/>
                        <circle class="db-ring-fg" cx="50" cy="50" r="40" stroke="#ea580c" style="--ring-to:<?=$leaveOff?>"/>
                    </svg>
                    <div class="db-ring-center"><?=$myLeave['remain']?><span class="db-ring-unit">dni</span></div>
                    <div class="db-ring-label">Urlop</div>
                </div>
                <div class="db-ring">
                    <svg viewBox="0 0 100 100">
                        <circle class="db-ring-bg" cx="50" cy="50" r="40"/>
                        <circle class="db-ring-fg" cx="50" cy="50" r="40" stroke="#f97316" style="--ring-to:<?=$otOff?>"/>
                    </svg>
                    <div class="db-ring-center"><?=$myOt?><span class="db-ring-unit">godz</span></div>
                    <div class="db-ring-label">Nadgodziny</div>
                </div>
            </div>
        </a>

        <!-- ── ANNOUNCEMENTS TILE ── -->
        <div class="db-tile" style="animation-delay:.2s">
            <div class="db-tile-head">
                <div class="db-tile-icon db-ann-icon"><svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></div>
                <div><div class="db-tile-title">Ogłoszenia</div><div class="db-tile-subtitle"><?=count($announcements)?> aktywnych</div></div>
            </div>
            <?php if(empty($announcements)):?>
            <div class="db-ann-empty">Brak ogłoszeń</div>
            <?php else:?>
            <div class="db-ann-list">
                <?php foreach($announcements as $ai => $ann): ?>
                <div class="db-ann-item <?=$ann['is_important']?'db-ann-item-imp':''?>" onclick="openAnn(<?=$ann['id']?>)" data-id="<?=$ann['id']?>">
                    <div class="db-ann-title">
                        <?=$ann['is_important']?'<span class="db-ann-badge">Ważne</span>':''?>
                        <?=h($ann['title'])?>
                    </div>
                    <div class="db-ann-date"><?=date('d.m.Y', strtotime($ann['created_at']))?><?=$ann['author']?' · '.h($ann['author']):''?></div>
                </div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
        </div>

        <!-- ── REQUESTS TILE ── -->
        <a href="forms.php" class="db-tile" style="animation-delay:.3s;text-decoration:none;color:inherit">
            <div class="db-tile-head">
                <div class="db-tile-icon db-req-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg></div>
                <div><div class="db-tile-title">Moje wnioski</div><div class="db-tile-subtitle">Status złożonych wniosków</div></div>
            </div>
            <div class="db-req-stats">
                <div class="db-req-stat db-req-pending">
                    <div class="db-req-num"><?=$reqStats['pending']?></div>
                    <div class="db-req-label">Oczekuje</div>
                </div>
                <div class="db-req-stat db-req-approved">
                    <div class="db-req-num"><?=$reqStats['approved']?></div>
                    <div class="db-req-label">Zaakceptowane</div>
                </div>
                <div class="db-req-stat db-req-rejected">
                    <div class="db-req-num"><?=$reqStats['rejected']?></div>
                    <div class="db-req-label">Odrzucone</div>
                </div>
            </div>
        </a>

        <!-- ── DUTIES TILE ── -->
        <a href="schedule.php" class="db-tile" style="animation-delay:.4s;text-decoration:none;color:inherit">
            <div class="db-tile-head">
                <div class="db-tile-icon db-dyz-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <div><div class="db-tile-title">Moje dyżury</div><div class="db-tile-subtitle"><?=$monthNames[$currentMonth]?> <?=$currentYear?> — <?=count($myDuties)?> dyżurów</div></div>
            </div>
            <?php if(empty($myDuties)):?>
            <div class="db-dyz-empty">Brak dyżurów w tym miesiącu</div>
            <?php else:?>
            <div class="db-dyz-list">
                <?php $dayNames=['Nd','Pn','Wt','Śr','Cz','Pt','So']; foreach($myDuties as $dz):
                    $dDate = strtotime($dz['entry_date']);
                    $dDay = (int)date('j', $dDate);
                    $dDow = $dayNames[(int)date('w', $dDate)];
                    $dTime = ($dz['shift_start'] && $dz['shift_end']) ? short_time($dz['shift_start']).' – '.short_time($dz['shift_end']) : 'godziny nieustalone';
                ?>
                <div class="db-dyz-item">
                    <div class="db-dyz-day"><div class="db-dyz-day-num"><?=$dDay?></div><div class="db-dyz-day-name"><?=$dDow?></div></div>
                    <div class="db-dyz-info"><?=h($dTime)?></div>
                </div>
                <?php endforeach;?>
            </div>
            <?php endif;?>
        </a>

        <!-- ── IMPORTANT LINKS TILE ── -->
        <div class="db-tile db-tile-links" style="animation-delay:.5s;grid-column:1/-1">
            <div class="db-tile-head">
                <div class="db-tile-icon db-links-icon"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                <div><div class="db-tile-title">Ważne strony</div><div class="db-tile-subtitle">Szybki dostęp do systemów SCK</div></div>
            </div>
            <div class="db-links-grid">
                <a href="https://admin.biletyna.pl/auth" target="_blank" class="db-link-card db-link-1">
                    <div class="db-link-icon-wrap">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                    </div>
                    <div class="db-link-name">Panel Biletyna.pl</div>
                    <div class="db-link-url">admin.biletyna.pl</div>
                    <div class="db-link-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg></div>
                    <div class="db-link-shine"></div>
                </a>
                <a href="https://pracownik.sck.strzegom.pl/" target="_blank" class="db-link-card db-link-2">
                    <div class="db-link-icon-wrap">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div class="db-link-name">SCK - System wynajmu</div>
                    <div class="db-link-url">pracownik.sck.strzegom.pl</div>
                    <div class="db-link-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg></div>
                    <div class="db-link-shine"></div>
                </a>
            </div>
        </div>
    </div>

<div class="db-footer">
    <img src="assets/logo_epracownik.png" alt="ePracownik" style="height:60px;margin-bottom:10px">
    <div style="font-size:10px;color:#666;">
        System ePracownik by
        <a href="mailto:t.kruszelnicki@sck.strzegom.pl"
           style="color:#444;text-decoration:none;font-weight:500;transition:0.2s;">
            Tomasz Kruszelnicki
        </a>
    </div>
</div>
</div>

<!-- Announcement modal -->
<div class="db-ann-modal" id="annModal" onclick="closeAnn(event)">
    <div class="db-ann-modal-box" onclick="event.stopPropagation()">
        <div class="db-ann-modal-title" id="annModalTitle"></div>
        <div class="db-ann-modal-meta" id="annModalMeta"></div>
        <div class="db-ann-modal-body" id="annModalBody"></div>
        <button class="btn btn-ghost db-ann-modal-close" onclick="closeAnn()">Zamknij</button>
    </div>
</div>

<script>
var annData=<?=json_encode(array_map(function($a){return['id'=>$a['id'],'title'=>$a['title'],'body'=>$a['body'],'date'=>date('d.m.Y H:i',strtotime($a['created_at'])),'author'=>$a['author']??'','imp'=>(bool)$a['is_important']];}, $announcements))?>;
function openAnn(id){
    var a=annData.find(function(x){return x.id==id;});
    if(!a)return;
    document.getElementById('annModalTitle').textContent=a.title;
    document.getElementById('annModalMeta').textContent=a.date+(a.author?' \u00b7 '+a.author:'')+(a.imp?' \u00b7 Wa\u017cne':'');
    document.getElementById('annModalBody').textContent=a.body;
    document.getElementById('annModal').classList.add('open');
}
function closeAnn(e){if(!e||e.target===e.currentTarget)document.getElementById('annModal').classList.remove('open');}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeAnn();});
</script>

<?php layout_end(); ?>
