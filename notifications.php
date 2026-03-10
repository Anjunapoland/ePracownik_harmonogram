<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

mark_notifications_read($user['id']);
$notifs = get_notifications($user['id'], 50);

// Clean stale dyzur notifications (dyzur removed from schedule)
$db = get_db();
$cleanNotifs = [];
$staleIds = [];
foreach ($notifs as $n) {
    if ($n['type'] === 'dyzur' && $n['related_date']) {
        $chk = $db->prepare("SELECT id FROM schedule_entries WHERE user_id=? AND entry_date=? AND shift_type='dyzur'");
        $chk->execute([$user['id'], $n['related_date']]);
        if (!$chk->fetchColumn()) {
            $staleIds[] = $n['id'];
            continue; // skip stale
        }
    }
    $cleanNotifs[] = $n;
}
// Remove stale from DB
if (!empty($staleIds)) {
    $ph = implode(',', array_fill(0, count($staleIds), '?'));
    $db->prepare("DELETE FROM notifications WHERE id IN ($ph)")->execute($staleIds);
}
$notifs = $cleanNotifs;

// Group by date
$grouped = [];
foreach ($notifs as $n) {
    $dateKey = date('Y-m-d', strtotime($n['created_at']));
    $grouped[$dateKey][] = $n;
}

layout_start('Powiadomienia');
?>

<style>
.ntf-wrap{max-width:720px;margin:0 auto;padding:16px 24px}
.ntf-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.ntf-title{font-size:24px;font-weight:800;color:#1c1917;letter-spacing:-.02em;display:flex;align-items:center;gap:12px}
.ntf-title-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.ntf-title-icon svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ntf-count{font-size:12px;font-weight:700;color:#78716c;background:#f5f5f4;padding:4px 12px;border-radius:99px}

/* Empty state */
.ntf-empty{text-align:center;padding:64px 24px}
.ntf-empty-icon{width:72px;height:72px;border-radius:20px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.ntf-empty-icon svg{width:32px;height:32px;stroke:#d6d3d1;fill:none;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
.ntf-empty-text{font-size:15px;font-weight:700;color:#a8a29e}
.ntf-empty-sub{font-size:12px;color:#d6d3d1;margin-top:4px}

/* Date group */
.ntf-date-group{margin-bottom:20px;opacity:0;animation:ntfSlide .4s ease-out forwards}
@keyframes ntfSlide{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.ntf-date-label{font-size:11px;font-weight:700;color:#a8a29e;text-transform:uppercase;letter-spacing:.06em;padding:0 4px 8px;display:flex;align-items:center;gap:8px}
.ntf-date-label::after{content:'';flex:1;height:1px;background:#f5f5f4}

/* Notification card */
.ntf-card{background:#fff;border:1.5px solid #f5f5f4;border-radius:14px;overflow:hidden;margin-bottom:8px;transition:border-color .2s,box-shadow .2s}
.ntf-card:hover{border-color:#e7e5e4;box-shadow:0 4px 16px rgba(0,0,0,.04)}
.ntf-card-inner{display:flex;align-items:flex-start;gap:14px;padding:14px 18px}

/* Icon */
.ntf-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ntf-icon svg{width:18px;height:18px;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ntf-icon-dyzur{background:linear-gradient(135deg,#fff7ed,#ffedd5)}
.ntf-icon-dyzur svg{stroke:#ea580c}
.ntf-icon-info{background:#f5f5f4}
.ntf-icon-info svg{stroke:#78716c}

/* Content */
.ntf-content{flex:1;min-width:0}
.ntf-content-title{font-size:13px;font-weight:700;color:#1c1917;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ntf-badge-new{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:2px 8px;border-radius:99px;background:linear-gradient(135deg,#ea580c,#f97316);color:#fff}
.ntf-content-body{font-size:12px;color:#57534e;line-height:1.6;margin-top:3px}
.ntf-content-time{font-size:10px;color:#a8a29e;margin-top:5px;display:flex;align-items:center;gap:4px}
.ntf-content-time svg{width:12px;height:12px;stroke:#d6d3d1;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* Dyzur card accent */
.ntf-card-dyzur{border-left:3px solid #ea580c}
.ntf-card-new{background:#fffbf7}
</style>

<div class="ntf-wrap">
    <div class="ntf-header">
        <div class="ntf-title">
            <div class="ntf-title-icon">
                <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </div>
            Powiadomienia
        </div>
        <?php if(!empty($notifs)):?>
        <div class="ntf-count"><?=count($notifs)?> powiadomie<?=count($notifs)==1?'nie':'n'?></div>
        <?php endif;?>
    </div>

    <?php if (empty($notifs)): ?>
    <div class="ntf-empty">
        <div class="ntf-empty-icon">
            <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
        </div>
        <div class="ntf-empty-text">Brak powiadomien</div>
        <div class="ntf-empty-sub">Gdy pojawi sie nowy dyzur lub zmiana, zobaczysz to tutaj</div>
    </div>
    <?php else: ?>
    <?php $gi=0; foreach($grouped as $dateKey => $items): $gi++; 
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if($dateKey === $today) $dateLabel = 'Dzisiaj';
        elseif($dateKey === $yesterday) $dateLabel = 'Wczoraj';
        else $dateLabel = date('d.m.Y', strtotime($dateKey));
    ?>
    <div class="ntf-date-group" style="animation-delay:<?=$gi*0.08?>s">
        <div class="ntf-date-label"><?=$dateLabel?></div>
        <?php foreach($items as $ni => $n):
            $isDyzur = $n['type'] === 'dyzur';
            $isNew = !$n['is_read'];
            $time = date('H:i', strtotime($n['created_at']));
        ?>
        <div class="ntf-card <?=$isDyzur?'ntf-card-dyzur':''?> <?=$isNew?'ntf-card-new':''?>">
            <div class="ntf-card-inner">
                <div class="ntf-icon <?=$isDyzur?'ntf-icon-dyzur':'ntf-icon-info'?>">
                    <?php if($isDyzur): ?>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <?php endif; ?>
                </div>
                <div class="ntf-content">
                    <div class="ntf-content-title">
                        <?=h($n['title'])?>
                        <?php if($isNew):?><span class="ntf-badge-new">Nowe</span><?php endif;?>
                    </div>
                    <div class="ntf-content-body"><?=h($n['body'])?></div>
                    <div class="ntf-content-time">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?=$time?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php layout_end(); ?>
