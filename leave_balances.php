<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$isAdmin = is_admin();
$myView = ($isAdmin && ($_GET['view'] ?? '') === 'my') || !$isAdmin;
$currentYear = (int)date('Y');
$selYear = max(2020, min(2099, (int)($_GET['year'] ?? $currentYear)));
$msg = ''; $mt = '';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'save_all') {
        $saved = 0;
        foreach (($_POST['rows'] ?? []) as $uid => $data) {
            $uid = (int)$uid; if ($uid <= 0) continue;
            $lp = max(0, round((float)($data['leave_prev'] ?? 0), 1));
            $lc = max(0, round((float)($data['leave_current'] ?? 0), 1));
            $ot = round((float)($data['overtime'] ?? 0), 1);
            $nt = trim($data['note'] ?? '');
            $stmt = $db->prepare('SELECT id FROM leave_balances WHERE user_id=? AND year=?');
            $stmt->execute([$uid, $selYear]);
            $ex = $stmt->fetchColumn();
            if ($ex) {
                $db->prepare('UPDATE leave_balances SET leave_prev_year=?, leave_current_year=?, overtime_hours=?, note=?, updated_by=?, updated_at=NOW() WHERE id=?')
                   ->execute([$lp, $lc, $ot, $nt ?: null, $user['id'], $ex]);
            } else {
                $db->prepare('INSERT INTO leave_balances (user_id, year, leave_prev_year, leave_current_year, overtime_hours, note, updated_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())')
                   ->execute([$uid, $selYear, $lp, $lc, $ot, $nt ?: null, $user['id']]);
            }
            $saved++;
        }
        $msg = "Zapisano dane dla $saved pracowników"; $mt = 'success';
    }
    if (($_POST['action'] ?? '') === 'reset_all') {
        $db->prepare('DELETE FROM leave_balances WHERE year=?')->execute([$selYear]);
        $msg = "Zresetowano wszystkie dane urlopowe i nadgodziny za rok $selYear"; $mt = 'success';
    }
}

$employees = ($isAdmin && !$myView) ? get_employees() : [get_db()->query("SELECT * FROM users WHERE id={$user['id']}")->fetch()];
$stmt = $db->prepare('SELECT lb.*, u.full_name AS updated_by_name FROM leave_balances lb LEFT JOIN users u ON u.id = lb.updated_by WHERE lb.year = ?');
$stmt->execute([$selYear]);
$balances = [];
foreach ($stmt->fetchAll() as $r) $balances[$r['user_id']] = $r;

$yearStart = sprintf('%04d-01-01', $selYear);
$yearEnd   = sprintf('%04d-12-31', $selYear);
$stTypes = get_shift_types();
$leaveCodes = [];
foreach ($stTypes as $code => $st) {
    $lbl = mb_strtolower($st['label']);
    if (in_array($code, ['urlop','urlop_na_zadanie']) || strpos($lbl, 'urlop') !== false) $leaveCodes[] = $code;
}
$usedLeave = [];
if (!empty($leaveCodes)) {
    $ph = implode(',', array_fill(0, count($leaveCodes), '?'));
    $params = array_merge($leaveCodes, [$yearStart, $yearEnd]);
    $stmt = $db->prepare("SELECT user_id, COUNT(*) as days FROM schedule_entries WHERE shift_type IN ($ph) AND entry_date BETWEEN ? AND ? GROUP BY user_id");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) $usedLeave[(int)$r['user_id']] = (int)$r['days'];
}

layout_start('Urlopy i nadgodziny');

$myB = $balances[$user['id']] ?? null;
$myLeave = calc_leave_remaining($user['id'], $selYear);
$myLp = $myLeave['prev_remain'];
$myLc = $myLeave['curr_remain'];
$myLpPool = $myLeave['prev_pool'];
$myLcPool = $myLeave['curr_pool'];
$myOt = $myB ? (float)$myB['overtime_hours'] : 0;
$myTotal = $myLeave['total'];
$myUsed = $myLeave['used'];
$myRemain = $myLeave['remain'];
$myPct = $myTotal > 0 ? min(100, round(($myUsed / $myTotal) * 100)) : 0;
$myUpdAt = $myB ? $myB['updated_at'] : null;
$myUpdBy = $myB ? ($myB['updated_by_name'] ?? '') : '';
?>

<?php if ($myView): ?>
<!-- ═══ PREMIUM EMPLOYEE DASHBOARD ═══ -->
<style>
.ld-wrap{font-family:'Inter',system-ui,-apple-system,sans-serif;padding:24px;max-width:900px;margin:0 auto}
.ld-header{margin-bottom:32px}
.ld-title{font-size:32px;font-weight:900;letter-spacing:-.02em;color:#1c1917;line-height:1.1}
.ld-title span{background:linear-gradient(135deg,#ea580c,#f97316,#fb923c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ld-subtitle{font-size:13px;color:#a8a29e;margin-top:4px;font-weight:400}
.ld-year-sel{display:inline-flex;align-items:center;gap:6px;background:#fafaf9;border:1px solid #e7e5e4;border-radius:10px;padding:6px 12px;font-size:13px;font-weight:600;color:#57534e}
.ld-year-sel select{border:none;background:none;font-weight:800;color:#1c1917;font-size:13px;cursor:pointer;font-family:inherit}
.ld-hero{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
@media(max-width:640px){.ld-hero{grid-template-columns:1fr}}
.ld-ring-card{background:linear-gradient(145deg,#1c1917,#292524);border-radius:24px;padding:36px;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden}
.ld-ring-card::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;background:radial-gradient(circle,rgba(234,88,12,.15),transparent 70%);border-radius:50%}
.ld-ring-card::after{content:'';position:absolute;bottom:-40px;left:-40px;width:150px;height:150px;background:radial-gradient(circle,rgba(251,146,60,.1),transparent 70%);border-radius:50%}
.ld-ring-svg{width:180px;height:180px;position:relative;z-index:1}
.ld-ring-svg svg{width:100%;height:100%;transform:rotate(-90deg)}
.ld-ring-bg{fill:none;stroke:#3f3f46;stroke-width:10}
.ld-ring-fg{fill:none;stroke:url(#ringGrad);stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset 1.5s cubic-bezier(.25,.46,.45,.94)}
.ld-ring-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2}
.ld-ring-num{font-size:52px;font-weight:900;color:#fff;letter-spacing:-.03em;line-height:1}
.ld-ring-unit{font-size:12px;color:#a8a29e;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
.ld-ring-label{font-size:12px;color:#78716c;margin-top:16px;font-weight:600;position:relative;z-index:1}
.ld-ring-label strong{color:#fb923c}
.ld-summary{display:flex;flex-direction:column;gap:12px;justify-content:center}
.ld-stat{background:#fff;border:1px solid #f5f5f4;border-radius:18px;padding:20px 24px;display:flex;align-items:center;gap:16px;transition:transform .2s,box-shadow .2s;opacity:0;transform:translateY(16px);animation:ldSlide .5s cubic-bezier(.4,0,.2,1) forwards}
.ld-stat:hover{transform:translateY(-3px)!important;box-shadow:0 12px 32px rgba(0,0,0,.08)}
.ld-stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;flex-shrink:0}
.ld-stat-icon svg{width:24px;height:24px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.ld-icon-prev{background:linear-gradient(135deg,#f97316,#ea580c)}
.ld-icon-curr{background:linear-gradient(135deg,#fb923c,#f97316)}
.ld-icon-used{background:linear-gradient(135deg,#fdba74,#fb923c)}
.ld-icon-ot{background:linear-gradient(135deg,#fed7aa,#fdba74)}
.ld-stat-info{flex:1;min-width:0}
.ld-stat-label{font-size:11px;color:#a8a29e;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.ld-stat-val{font-size:28px;font-weight:900;color:#1c1917;letter-spacing:-.02em;line-height:1.1;margin-top:2px}
.ld-stat-sub{font-size:11px;color:#78716c;margin-top:1px}
@keyframes ldSlide{to{opacity:1;transform:translateY(0)}}
.ld-progress{background:#fff;border:1px solid #f5f5f4;border-radius:20px;padding:24px 28px;margin-bottom:20px;opacity:0;animation:ldSlide .5s .5s cubic-bezier(.4,0,.2,1) forwards}
.ld-prog-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:12px}
.ld-prog-title{font-size:13px;font-weight:700;color:#1c1917}
.ld-prog-pct{font-size:28px;font-weight:900;background:linear-gradient(135deg,#ea580c,#fb923c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ld-bar{height:12px;background:#f5f5f4;border-radius:99px;overflow:hidden;position:relative}
.ld-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#ea580c,#f97316,#fb923c);transition:width 1.5s cubic-bezier(.25,.46,.45,.94);position:relative}
.ld-bar-fill::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent 60%,rgba(255,255,255,.3));border-radius:99px}
.ld-bar-labels{display:flex;justify-content:space-between;margin-top:6px;font-size:11px;color:#a8a29e;font-weight:500}
.ld-footer{text-align:center;font-size:11px;color:#a8a29e;padding:8px;opacity:0;animation:ldSlide .4s .8s forwards}
.ld-footer strong{color:#78716c}
</style>

<div class="ld-wrap">
    <div class="ld-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
            <div class="ld-title">Moje <span>urlopy</span></div>
            <div class="ld-subtitle">Podsumowanie urlopowe i nadgodziny za wybrany rok</div>
        </div>
        <form method="get" class="ld-year-sel">
            <?php if($isAdmin):?><input type="hidden" name="view" value="my"><?php endif;?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <select name="year" onchange="this.form.submit()">
                <?php for($y=$currentYear+4;$y>=$currentYear-3;$y--): ?>
                <option value="<?=$y?>" <?=$y===$selYear?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="ld-hero">
        <!-- RING CHART -->
        <div class="ld-ring-card">
            <div class="ld-ring-svg">
                <svg viewBox="0 0 140 140" width="180" height="180">
                    <defs><linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#ea580c"/><stop offset="50%" stop-color="#f97316"/><stop offset="100%" stop-color="#fb923c"/></linearGradient></defs>
                    <circle class="ld-ring-bg" cx="70" cy="70" r="58"/>
                    <circle class="ld-ring-fg" cx="70" cy="70" r="58" stroke-dasharray="364.4" stroke-dashoffset="364.4" data-pct="<?=$myPct?>"/>
                </svg>
                <div class="ld-ring-center">
                    <div class="ld-ring-num" data-val="<?=$myRemain?>"><?=$myRemain?></div>
                    <div class="ld-ring-unit">dni</div>
                </div>
            </div>
            <div class="ld-ring-label">Pozosta<?=$myRemain==1?'l':'lo'?> z <strong><?=$myTotal?></strong> przysugujacych</div>
        </div>

        <!-- STAT CARDS -->
        <div class="ld-summary">
            <div class="ld-stat" style="animation-delay:.15s">
                <div class="ld-stat-icon ld-icon-prev"><svg viewBox="0 0 24 24"><path d="M8 2v4M16 2v4M3 10h18"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg></div>
                <div class="ld-stat-info">
                    <div class="ld-stat-label">Urlop zalegly</div>
                    <div class="ld-stat-val" data-val="<?=$myLp?>"><?=$myLp?></div>
                    <div class="ld-stat-sub">z <?=$myLpPool?> dn. (rok <?=$selYear-1?>)</div>
                </div>
            </div>
            <div class="ld-stat" style="animation-delay:.25s">
                <div class="ld-stat-icon ld-icon-curr"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                <div class="ld-stat-info">
                    <div class="ld-stat-label">Urlop biezacy</div>
                    <div class="ld-stat-val" data-val="<?=$myLc?>"><?=$myLc?></div>
                    <div class="ld-stat-sub">z <?=$myLcPool?> dn. (rok <?=$selYear?>)</div>
                </div>
            </div>
            <div class="ld-stat" style="animation-delay:.35s">
                <div class="ld-stat-icon ld-icon-used"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div>
                <div class="ld-stat-info">
                    <div class="ld-stat-label">Wykorzystano</div>
                    <div class="ld-stat-val" data-val="<?=$myUsed?>"><?=$myUsed?></div>
                    <div class="ld-stat-sub">dni urlopu</div>
                </div>
            </div>
            <div class="ld-stat" style="animation-delay:.45s">
                <div class="ld-stat-icon ld-icon-ot"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <div class="ld-stat-info">
                    <div class="ld-stat-label">Nadgodziny</div>
                    <div class="ld-stat-val ld-hours" data-val="<?=$myOt?>"><?=$myOt?>h</div>
                    <div class="ld-stat-sub">godzin nadliczbowych</div>
                </div>
            </div>
        </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="ld-progress">
        <div class="ld-prog-head">
            <div class="ld-prog-title">Wykorzystanie urlopu</div>
            <div class="ld-prog-pct"><?=$myPct?>%</div>
        </div>
        <div class="ld-bar">
            <div class="ld-bar-fill" data-width="<?=$myPct?>" style="width:0%"></div>
        </div>
        <div class="ld-bar-labels"><span>0 dni</span><span><?=$myTotal?> dni</span></div>
    </div>

    <?php if($myUpdAt): ?>
    <div class="ld-footer">
        Zaktualizowano <strong><?=date('d.m.Y',strtotime($myUpdAt))?></strong> o <?=date('H:i',strtotime($myUpdAt))?><?=$myUpdBy?' &middot; '.h($myUpdBy):''?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
    // Animate ring
    var fg=document.querySelector('.ld-ring-fg');
    if(fg){var p=parseInt(fg.dataset.pct)||0;setTimeout(function(){fg.style.strokeDashoffset=364.4-(364.4*p/100);},300);}
    // Animate bar
    var bar=document.querySelector('.ld-bar-fill');
    if(bar)setTimeout(function(){bar.style.width=bar.dataset.width+'%';},500);
    // Animate numbers with counting effect
    document.querySelectorAll('.ld-stat-val').forEach(function(el){
        var t=parseFloat(el.dataset.val)||0,isH=el.classList.contains('ld-hours'),s=isH?'h':'';
        var dur=1000,st=null;
        function tick(ts){
            if(!st)st=ts;
            var pr=Math.min((ts-st)/dur,1);
            var ease=1-Math.pow(1-pr,3);
            var v=t*ease;
            el.textContent=(t%1===0?Math.round(v):v.toFixed(1))+s;
            if(pr<1)requestAnimationFrame(tick);
        }
        setTimeout(function(){requestAnimationFrame(tick);},200);
    });
    // Animate ring center number
    var rn=document.querySelector('.ld-ring-num');
    if(rn){
        var t2=parseFloat(rn.dataset.val)||0,dur2=1200,st2=null;
        function tick2(ts){
            if(!st2)st2=ts;
            var pr=Math.min((ts-st2)/dur2,1);
            var ease=1-Math.pow(1-pr,3);
            rn.textContent=Math.round(t2*ease);
            if(pr<1)requestAnimationFrame(tick2);
        }
        setTimeout(function(){requestAnimationFrame(tick2);},400);
    }
});
</script>

<?php else: ?>
<!-- ═══ ADMIN TABLE VIEW ═══ -->
<div class="page-head"><h2>🏖️ Urlopy i nadgodziny — <?=$selYear?></h2>
    <div class="page-acts">
        <form method="get" class="filter-form">
            <select name="year" class="input input-sm" onchange="this.form.submit()">
                <?php for($y=$currentYear+4;$y>=$currentYear-3;$y--): ?>
                <option value="<?=$y?>" <?=$y===$selYear?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>
<?php if($msg):?><div class="alert alert-<?=$mt?>"><?=h($msg)?></div><?php endif;?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save_all">
<div class="card">
<table class="dtable">
<thead><tr>
    <th>Pracownik</th><th>Dział</th>
    <th style="text-align:center">Urlop zaległy<br><small>(z <?=$selYear-1?>)</small></th>
    <th style="text-align:center">Urlop bieżący<br><small>(<?=$selYear?>)</small></th>
    <th style="text-align:center">Suma</th>
    <th style="text-align:center">Wykorzystano</th>
    <th style="text-align:center">Pozostało</th>
    <th style="text-align:center">Nadgodziny<br><small>(godz.)</small></th>
    <th>Notatka</th><th style="text-align:center">Aktualizacja</th>
</tr></thead>
<tbody>
<?php foreach($employees as $emp):
    $uid=$emp['id']; $b=$balances[$uid]??null;
    $lp=$b?(float)$b['leave_prev_year']:0; $lc=$b?(float)$b['leave_current_year']:0;
    $ot=$b?(float)$b['overtime_hours']:0; $nt=$b?($b['note']??''):'';
    $total=$lp+$lc; $used=$usedLeave[$uid]??0; $rem=$total-$used;
    $updAt=$b?$b['updated_at']:null; $updBy=$b?($b['updated_by_name']??''):'';
?>
<tr>
    <td><strong><?=h($emp['full_name'])?></strong></td>
    <td><?=h($emp['department'])?></td>
    <td style="text-align:center"><input type="number" name="rows[<?=$uid?>][leave_prev]" value="<?=$lp?>" step="0.5" min="0" max="99" class="input lb-input"></td>
    <td style="text-align:center"><input type="number" name="rows[<?=$uid?>][leave_current]" value="<?=$lc?>" step="0.5" min="0" max="99" class="input lb-input"></td>
    <td style="text-align:center"><strong class="lb-total"><?=$total?></strong></td>
    <td style="text-align:center"><span class="badge <?=$used>0?'badge-warn':'badge-ok'?>"><?=$used?> dni</span></td>
    <td style="text-align:center"><strong class="<?=$rem<0?'lb-neg':($rem<=3?'lb-warn':'lb-ok')?>"><?=$rem?></strong></td>
    <td style="text-align:center"><input type="number" name="rows[<?=$uid?>][overtime]" value="<?=$ot?>" step="0.5" class="input lb-input"></td>
    <td><input type="text" name="rows[<?=$uid?>][note]" value="<?=h($nt)?>" class="input lb-note" placeholder="np. ekwiwalent"></td>
    <td class="lb-meta"><?php if($updAt):?><div class="lb-date"><?=date('d.m.Y',strtotime($updAt))?></div><div class="lb-time"><?=date('H:i',strtotime($updAt))?></div><?php if($updBy):?><div class="lb-by"><?=h($updBy)?></div><?php endif;?><?php else:?><span class="text-muted">—</span><?php endif;?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div style="padding:16px 24px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <button type="submit" class="btn btn-primary">💾 Zapisz wszystkie zmiany</button>
    <span class="text-muted" style="font-size:12px">Zmiany dotyczą roku <?=$selYear?></span>
    <div style="margin-left:auto"></div>
</div>
</form>
<form method="post" style="padding:0 24px 16px" onsubmit="return confirm('UWAGA: Czy na pewno chcesz zresetować wszystkie dane urlopowe i nadgodziny za rok <?=$selYear?>?\n\nTa operacja wyzeruje urlopy zaległe, bieżące, nadgodziny i notatki dla WSZYSTKICH pracowników.\n\nTej operacji nie można cofnąć!')">
    <?=csrf_field()?><input type="hidden" name="action" value="reset_all">
    <button type="submit" class="btn btn-ghost btn-danger-ghost" style="font-size:12px">🔄 Resetuj wszystkie dane za <?=$selYear?></button>
</form>
<script>
document.querySelectorAll('.lb-input').forEach(function(inp){
    inp.addEventListener('input',function(){
        var tr=this.closest('tr'),ins=tr.querySelectorAll('.lb-input');
        var p=parseFloat(ins[0].value)||0,c=parseFloat(ins[1].value)||0,t=p+c;
        tr.querySelector('.lb-total').textContent=t;
        var u=parseInt(tr.querySelector('.badge').textContent)||0,r=t-u;
        var re=tr.querySelector('.lb-ok,.lb-warn,.lb-neg');
        if(re){re.textContent=r;re.className=r<0?'lb-neg':(r<=3?'lb-warn':'lb-ok');}
    });
});
</script>
<?php endif; ?>
<?php layout_end(); ?>
