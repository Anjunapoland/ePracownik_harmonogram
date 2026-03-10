<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

$year  = max(2020, min(2099, (int)($_GET['y'] ?? date('Y'))));
$month = max(1, min(12, (int)($_GET['m'] ?? date('n'))));
$dim   = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$prevM = $month-1; $prevY = $year; if ($prevM<1){$prevM=12;$prevY--;}
$nextM = $month+1; $nextY = $year; if ($nextM>12){$nextM=1;$nextY++;}

$filterUid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$filterDept = $_GET['dept'] ?? '';
$viewMode = $_GET['view'] ?? ''; // 'all', 'dept', 'me' or empty

if (is_admin()) {
    if ($filterUid) {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id=? AND active=1');
        $stmt->execute([$filterUid]);
        $found = $stmt->fetch();
        $employees = $found ? [$found] : get_employees();
    } elseif ($filterDept !== '') {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE department=? AND active=1 ORDER BY full_name');
        $stmt->execute([$filterDept]);
        $employees = $stmt->fetchAll();
    } else {
        $employees = get_employees();
    }
} else {
    // Check if employee can view others
    $canViewAll = employee_can_view_all($user);
    if ($canViewAll) {
        if ($viewMode === 'me') {
            // Only own schedule
            $stmt = get_db()->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute([$user['id']]);
            $employees = [$stmt->fetch()];
        } elseif ($viewMode === 'dept' && $filterDept) {
            // Department filter
            $stmt = get_db()->prepare('SELECT * FROM users WHERE department=? AND active=1 ORDER BY full_name');
            $stmt->execute([$filterDept]);
            $employees = $stmt->fetchAll();
        } else {
            // All employees
            $viewMode = 'all';
            $employees = get_employees();
        }
    } else {
        // Restricted: only own schedule
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$user['id']]);
        $employees = [$stmt->fetch()];
    }
}

$entries = get_entries_for_month($year, $month);
$totalEntries = 0; foreach($entries as $ue) $totalEntries += count($ue);
$allEmps = is_admin() ? get_employees() : $employees;
$shiftTypes = get_shift_types();
$restViolations = is_admin() ? check_rest_violations($entries, $year, $month, $dim) : [];
$departments = [];
$allDepts = [];
if (is_admin()) { foreach($allEmps as $ae) $allDepts[$ae['department']] = true; $allDepts = array_keys($allDepts); }
if (is_admin() || (!is_admin() && !empty($canViewAll))) {
    $departments = get_departments();
}

// User extras (for everyone)
$myDyzury    = get_user_dyzury($user['id'], 10);
$unreadCount = get_unread_count($user['id']);

layout_start(MONTH_NAMES_PL[$month].' '.$year);
?>

<?php if ($unreadCount > 0): ?>
<div class="alert alert-warn" style="margin:16px 24px 0;display:flex;align-items:center;gap:10px">
    🔔 Masz <strong><?= $unreadCount ?></strong> nieprzeczytane powiadomienia
    <a href="notifications.php" class="btn btn-sm btn-primary" style="margin-left:auto">Zobacz</a>
</div>
<?php endif; ?>

<?php if (!empty($myDyzury)):
    $dyzCount = count($myDyzury);
    $nextDyz = $myDyzury[0];
    $nextDate = date('d.m.Y', strtotime($nextDyz['entry_date']));
    $nextDow = DAY_NAMES_PL[(int)date('w', strtotime($nextDyz['entry_date']))];
    $nextTime = ($nextDyz['shift_start'] && $nextDyz['shift_end'])
        ? short_time($nextDyz['shift_start']).' – '.short_time($nextDyz['shift_end']) : '';
    $daysUntil = max(0, (int)((strtotime($nextDyz['entry_date']) - time()) / 86400));
?>
<div class="dyz dyz-unread" id="dyzBanner">
    <div class="dyz-bar" onclick="toggleDyz()">
        <div class="dyz-left">
            <div class="dyz-pulse-wrap"><div class="dyz-pulse-dot"></div><div class="dyz-pulse-ring"></div></div>
            <div class="dyz-bar-info">
                <div class="dyz-bar-title">Nadchodzące dyżury <span class="dyz-count"><?=$dyzCount?></span></div>
                <div class="dyz-bar-sub">Najbliższy: <strong><?=$nextDate?></strong> (<?=$nextDow?>)<?=$nextTime?' · '.$nextTime:''?><?=$daysUntil>0?" · za {$daysUntil} dn.":' · dzisiaj'?></div>
            </div>
        </div>
        <div class="dyz-toggle">
            <svg class="dyz-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
    </div>
    <div class="dyz-body">
        <?php foreach ($myDyzury as $di => $dz):
            $dd = date('d.m.Y', strtotime($dz['entry_date']));
            $dw = DAY_NAMES_PL[(int)date('w', strtotime($dz['entry_date']))];
            $dh = ($dz['shift_start'] && $dz['shift_end'])
                ? short_time($dz['shift_start']).' – '.short_time($dz['shift_end']) : 'godziny nie ustalone';
            $dhrs = calc_hours($dz['shift_start'], $dz['shift_end']);
            $dzu = max(0, (int)((strtotime($dz['entry_date']) - time()) / 86400));
        ?>
        <div class="dyz-entry" style="animation-delay:<?=$di*0.07?>s">
            <div class="dyz-entry-left">
                <div class="dyz-entry-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="dyz-entry-info">
                    <div class="dyz-entry-date"><?=$dd?> <span>(<?=$dw?>)</span></div>
                    <div class="dyz-entry-time"><?=h($dh)?><?=$dhrs>0?" · {$dhrs}h":''?></div>
                </div>
            </div>
            <div class="dyz-entry-right">
                <?php if($dzu <= 1): ?><span class="dyz-badge dyz-badge-urgent"><?=$dzu==0?'Dziś':'Jutro'?></span>
                <?php elseif($dzu <= 3): ?><span class="dyz-badge dyz-badge-soon">Za <?=$dzu?> dn.</span>
                <?php else: ?><span class="dyz-badge">Za <?=$dzu?> dn.</span><?php endif; ?>
            </div>
            <?php if($dz['note']):?><div class="dyz-entry-note"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> <?=h($dz['note'])?></div><?php endif;?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
function toggleDyz(){
    var b=document.getElementById('dyzBanner'),o=b.classList.toggle('dyz-open');
    if(o){b.classList.remove('dyz-unread');b.classList.add('dyz-read');try{localStorage.setItem('dyz_r_<?=$year?>_<?=$month?>','<?=md5(json_encode($myDyzury))?>');}catch(e){}}
}
(function(){try{var k=localStorage.getItem('dyz_r_<?=$year?>_<?=$month?>');if(k==='<?=md5(json_encode($myDyzury))?>'){var b=document.getElementById('dyzBanner');b.classList.remove('dyz-unread');b.classList.add('dyz-read');}}catch(e){}})();
</script>
<?php endif; ?>
<?php
$navExtra = '';
if ($filterUid) $navExtra .= "&uid=$filterUid";
if (!empty($viewMode) && $viewMode !== 'all') $navExtra .= "&view=$viewMode";
if ($filterDept) $navExtra .= "&dept=".urlencode($filterDept);
?>
<div class="page-head">
    <div class="month-nav">
        <a href="?y=<?=$prevY?>&m=<?=$prevM?><?=$navExtra?>" class="btn btn-ghost">←</a>
        <h2><?= MONTH_NAMES_PL[$month] ?> <?= $year ?></h2>
        <a href="?y=<?=$nextY?>&m=<?=$nextM?><?=$navExtra?>" class="btn btn-ghost">→</a>
    </div>
    <?php if (is_admin()): ?>
    <div class="page-acts">
        <form method="get" class="filter-form">
            <input type="hidden" name="y" value="<?=$year?>">
            <input type="hidden" name="m" value="<?=$month?>">
            <select name="dept" class="input input-sm" onchange="this.form.submit()">
                <option value="">Wszystkie działy</option>
                <?php foreach($departments as $dept): ?>
                <option value="<?=h($dept)?>" <?=$filterDept===$dept?'selected':''?>><?=h($dept)?></option>
                <?php endforeach; ?>
            </select>
            <select name="uid" class="input input-sm" onchange="this.form.submit()">
                <option value="0">Wszyscy pracownicy (<?=count($allEmps)?>)</option>
                <?php foreach($allEmps as $e): ?>
                <option value="<?=$e['id']?>" <?=$filterUid==$e['id']?'selected':''?>><?=h($e['full_name'])?> — <?=h($e['department'])?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="hint">Kliknij komórkę = edycja · Kliknij imię = edycja miesiąca · Filtruj działem</span>
        <button class="btn btn-ghost" onclick="openCopy()">📋 Kopiuj miesiąc</button>
        <button class="btn btn-ghost" onclick="openShiftMgr()">🎨 Typy zmian</button>
        <button class="btn btn-ghost" onclick="openAutoFill()">⚡ Autouzupełnianie</button>
        <button class="btn btn-ghost btn-danger-ghost" onclick="openClearMonth()">🗑 Wyczyść miesiąc</button>
    </div>
    <?php else: ?>
    <div class="page-acts">
        <?php if (!empty($canViewAll)): ?>
        <div class="view-tabs">
            <a href="?y=<?=$year?>&m=<?=$month?>&view=me" class="vtab <?=$viewMode==='me'?'vtab-act':''?>">👤 Tylko ja</a>
            <?php foreach ($departments as $dept): ?>
            <a href="?y=<?=$year?>&m=<?=$month?>&view=dept&dept=<?=urlencode($dept)?>" class="vtab <?=($viewMode==='dept'&&$filterDept===$dept)?'vtab-act':''?>"><?=h($dept)?></a>
            <?php endforeach; ?>
            <a href="?y=<?=$year?>&m=<?=$month?>&view=all" class="vtab <?=$viewMode==='all'?'vtab-act':''?>">👥 Wszyscy</a>
        </div>
        <span class="hint">Przeciągnij aby przewijać</span>
        <?php else: ?>
        <span class="hint">Harmonogram jest zarządzany przez administratora · Przeciągnij aby przewijać</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if(is_admin()): ?>
<div class="multi-actions" id="multiBar" style="display:none">
    <div class="multi-title">🧩 Zaznaczono <strong id="selCount">0</strong> komórek</div>
    <div class="multi-ctrls">
        <select id="msType" class="input input-sm" onchange="msTc()">
            <?php foreach($shiftTypes as $c=>$s): ?>
            <option value="<?=$c?>" data-s="<?=h($s['start'])?>" data-e="<?=h($s['end'])?>"><?=h($s['label'])?><?=$s['start']&&$s['end']?" ({$s['start']}-{$s['end']})":''?></option>
            <?php endforeach; ?>
        </select>
        <input type="time" id="msS" class="input input-sm" placeholder="Od">
        <input type="time" id="msE" class="input input-sm" placeholder="Do">
        <input type="text" id="msN" class="input input-sm" placeholder="Notatka (opcjonalna)">
        <button class="btn btn-primary btn-sm" onclick="applyMulti()">Zastosuj do zaznaczonych</button>
        <button class="btn btn-ghost btn-sm" onclick="clearSelection()">Wyczyść zaznaczenie</button>
    </div>
    <div class="hint">Ctrl+klik — wielokrotny wybór, Shift+klik — zakres, dwuklik — edycja pojedynczej komórki.</div>
</div>
<?php endif; ?>

<?php if(is_admin() && !empty($restViolations)):
    $totalV = 0;
    $vDetails = [];
    foreach($restViolations as $vuid => $vdays) {
        foreach($vdays as $vd => $vi) {
            $totalV++;
            $vName = '';
            foreach($allEmps as $ve) { if($ve['id']==$vuid) { $vName=$ve['full_name']; break; } }
            $vDetails[] = ['name'=>$vName, 'day'=>$vd, 'gap'=>$vi['gap'], 'prev'=>$vi['prev_end'], 'next'=>$vi['next_start']];
        }
    }
?>
<div class="rest-panel" id="restPanel">
    <div class="rest-panel-bar" onclick="document.getElementById('restPanel').classList.toggle('rest-panel-open')">
        <span class="rest-panel-icon">&#9888;&#65039;</span>
        <span class="rest-panel-title">Naruszenie 11h odpoczynku <strong>(<?=$totalV?>)</strong></span>
        <span class="rest-panel-hint">Art. 132 Kodeksu pracy</span>
        <svg class="rest-panel-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="rest-panel-body">
        <?php foreach($vDetails as $vd): ?>
        <div class="rest-panel-item">
            <strong><?=h($vd['name'])?></strong> &mdash; dz. <?=$vd['day']?>
            <span class="rest-gap"><?=$vd['gap']?>h przerwy</span>
            <span class="rest-detail">(do <?=$vd['prev']?> &rarr; od <?=$vd['next']?>)</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="sched-wrap" id="sw">
<table class="sched">
<thead><tr>
    <th class="sc sc1">Pracownik</th>
    <th class="sc sc2">Godz.</th>
    <?php for($d=1;$d<=$dim;$d++):
        $dow=(int)date('w',mktime(0,0,0,$month,$d,$year)); $we=$dow===0||$dow===6;
    ?>
    <th class="col-day <?=$we?'we':''?>"><div><?=$d?></div><div class="dn"><?=DAY_NAMES_PL[$dow]?></div></th>
    <?php endfor; ?>
</tr></thead>
<tbody>
<?php
// Build department color map
$deptColors=['SCK'=>'#ea580c','CAS "Karmel"'=>'#7c3aed','Kadry'=>'#0891b2'];
$colorPool=['#2563eb','#16a34a','#d946ef','#dc2626','#ca8a04','#0d9488'];
$ci=0;
foreach($employees as $ce){
    $d=$ce['department'];
    if(!isset($deptColors[$d])){$deptColors[$d]=$colorPool[$ci%count($colorPool)];$ci++;}
}
$prevDept='';
foreach($employees as $idx=>$emp):
    $deptColor=$deptColors[$emp['department']]??'#78716c';
    // Department separator row
    if(!$filterDept && !$filterUid && $emp['department']!==$prevDept):
        $prevDept=$emp['department'];
        if($idx>0):
?>
<tr class="dept-spacer"><td colspan="<?=2+$dim?>"></td></tr>
<?php endif; ?>
<tr class="dept-sep"><td colspan="<?=2+$dim?>"><div class="dept-sep-inner"><span class="dept-sep-dot" style="background:<?=$deptColor?>"></span><?=h($prevDept)?><div class="dept-sep-line" style="background:linear-gradient(90deg,<?=$deptColor?>33,transparent)"></div><span class="dept-sep-count"><?php
    $dc=0; foreach($employees as $ce) if($ce['department']===$prevDept) $dc++;
    echo $dc;
?> os.</span></div></td></tr>
<?php endif;
    $euid=$emp['id']; $ue=$entries[$euid]??[];
    $hrs=month_hours($ue,$dim);
    $frac=number_format((float)$emp['employment_fraction'],2);
    $norm=FRACTIONS[$frac]??160;
    $hc=$hrs>$norm?'over':($hrs<$norm*0.9?'under':'ok');
    $isMe=$emp['id']===$user['id'];
?>
<tr class="<?=$isMe&&!is_admin()?'my-row':''?>">
    <td class="sc sc1" style="border-left:3px solid <?=$deptColor?>"><?php if(is_admin()):?><a href="#" class="en en-link" onclick="openBulk(<?=$euid?>,'<?=h($emp['full_name'])?>');return false"><?=h($emp['full_name'])?></a><?php else:?><div class="en"><?=h($emp['full_name'])?><?=$isMe&&!is_admin()?' <small>(Ty)</small>':''?></div><?php endif;?><div class="ed"><?=h($emp['department'])?></div></td>
    <td class="sc sc2 hrs-<?=$hc?>"><?=$hrs?>/<?=$norm?></td>
    <?php for($d=1;$d<=$dim;$d++):
        $dow=(int)date('w',mktime(0,0,0,$month,$d,$year)); $we=$dow===0||$dow===6;
        $e=$ue[$d]??null;
        $bg=$we?'#fef9f4':'#fff'; $fg='#78716c'; $label=''; $cellH=0; $noTime=false;
        if($e){
            $st=$e['shift_type'];
            $stDef=$shiftTypes[$st]??[];
            $eStart=$e['shift_start']?:($stDef['start']??null);
            $eEnd=$e['shift_end']?:($stDef['end']??null);
            $bg=shift_color($st); $fg=shift_text($st);
            $stLabelFull=$stDef['label']??$st;
            $stLabelLc=mb_strtolower($stLabelFull);
            $noTime=in_array($st,['urlop','urlop_na_zadanie','chorobowe','wolne','brak'])
                || strpos($stLabelLc,'urlop')!==false || strpos($stLabelLc,'chorobow')!==false;
            if($noTime){$eStart=null;$eEnd=null;}
            if($st==='wolne') $label='W';
            elseif($st==='brak') $label='X';
            elseif($noTime) $label=$stLabelFull;
            elseif($st==='swieto') $label=$e['note']?mb_substr($e['note'],0,10):'Święto';
            elseif($st==='dyzur'){
                $label='DYŻUR';
                if($eStart&&$eEnd) $label=short_time($eStart).'-'.short_time($eEnd);
            }
            else{
                if($eStart&&$eEnd) $label=short_time($eStart).'-'.short_time($eEnd);
                else $label=mb_substr(shift_label($st),0,6);
            }
            $cellH=$noTime?0:calc_hours($eStart,$eEnd);
        }
        $da='';
        if(is_admin()){
            $da=sprintf('data-uid="%d" data-day="%d" data-date="%04d-%02d-%02d"',$euid,$d,$year,$month,$d);
            if($e) $da.=sprintf(' data-type="%s" data-start="%s" data-end="%s" data-note="%s" data-eid="%d"',h($e['shift_type']),h($e['shift_start']??''),h($e['shift_end']??''),h($e['note']??''),$e['id']);
        }
    ?>
    <?php $rv = $restViolations[$euid][$d] ?? null; ?>
    <td class="cell <?=$we?'we':''?> <?=is_admin()?'ed':''?> <?=($e&&$e['shift_type']==='dyzur')?'cell-dyzur':''?> <?=$noTime&&$label&&mb_strlen($label)>6?'cell-wrap':''?> <?=$rv?'cell-rest-warn':''?>"
        style="background:<?=$bg?>;color:<?=$fg?>"
        <?=$da?>
        <?=is_admin()?'onclick="sc(this,event)" ondblclick="oe(this)"':''?>
        <?php if($rv): ?>title="&#9888; Przerwa <?=$rv['gap']?>h (min. 11h) &#10;Poprzednia zmiana: do <?=$rv['prev_end']?> &#10;Ta zmiana: od <?=$rv['next_start']?> &#10;Art. 132 Kodeksu pracy"
        <?php elseif($e&&$e['note']): ?>title="<?=h($e['note'])?>"<?php endif; ?>>
        <?php if($rv):?><div class="rest-warn-icon">&#9888;</div><?php endif;?>
        <div class="cl"><?=h($label)?></div>
        <?php if($cellH>0):?><div class="ch"><?=$cellH?>h</div><?php endif;?>
    </td>
    <?php endfor; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="legend">
    <?php foreach($shiftTypes as $c=>$s): if($c==='standard')continue; ?>
    <span class="li"><span class="ld" style="background:<?=$s['color']?>"></span><?=h($s['label'])?></span>
    <?php endforeach; ?>
</div>

<?php if(is_admin()): ?>
<!-- EDIT MODAL (admin only) -->
<div class="overlay" id="mo" style="display:none" onclick="ce(event)">
<div class="modal" onclick="event.stopPropagation()">
    <div class="mh"><div><h3>Edycja zmiany</h3><div class="ms" id="ms"></div></div><button class="mc" onclick="ce()">×</button></div>
    <div class="mb">
        <input type="hidden" id="fUid"><input type="hidden" id="fDate"><input type="hidden" id="fEid" value="">
        <label class="lbl">Typ zmiany</label>
        <select id="fType" class="input" onchange="tc()">
            <?php foreach($shiftTypes as $c=>$s): ?>
            <option value="<?=$c?>" data-s="<?=h($s['start'])?>" data-e="<?=h($s['end'])?>"><?=h($s['label'])?><?=$s['start']&&$s['end']?" ({$s['start']}-{$s['end']})":''?></option>
            <?php endforeach; ?>
        </select>
        <div id="dyzurHint" class="dyzur-hint" style="display:none">🚨 Pracownik otrzyma powiadomienie o przypisanym dyżurze</div>
        <div id="timeRow" class="g2">
            <div><label class="lbl">Godzina od</label><input type="time" id="fS" class="input" oninput="calcH()"></div>
            <div><label class="lbl">Godzina do</label><input type="time" id="fE" class="input" oninput="calcH()"></div>
        </div>
        <div class="hrs-box" id="hb" style="display:none">⏱ <strong id="hv">0</strong> godzin</div>
        <div id="noTimeHint" class="bulk-info" style="display:none"></div>
        <label class="lbl" id="noteLbl">Notatka</label>
        <textarea id="fN" class="input" rows="2" placeholder=""></textarea>
    </div>
    <div class="mf">
        <button class="btn btn-primary" onclick="se()">✓ Zapisz</button>
        <button class="btn btn-danger" id="bd" onclick="de()" style="display:none">Usuń</button>
        <button class="btn btn-ghost" onclick="ce()">Anuluj</button>
    </div>
</div>
</div>

<script>
const TK='<?=csrf_token()?>';
const CUR_Y=<?=$year?>,CUR_M=<?=$month?>;
const NO_TIME=['urlop','urlop_na_zadanie','chorobowe','wolne','brak'];
const selectedCells=new Map();
let lastPickedCell=null;

function isNoTimeShift(code,label){
    const c=(code||'').toLowerCase();
    const l=(label||'').toLowerCase();
    return NO_TIME.includes(c) || l.includes('urlop') || l.includes('chorobow');
}

function _ml(){document.body.classList.add('modal-open')}
function _mu(){document.body.classList.remove('modal-open')}
function _cellKey(td){return td.dataset.uid+'|'+td.dataset.date}
function _toggleSelected(td,on){
    const k=_cellKey(td);
    if(on){selectedCells.set(k,td);td.classList.add('cell-selected');}
    else{selectedCells.delete(k);td.classList.remove('cell-selected');}
}
function clearSelection(){
    selectedCells.forEach(td=>td.classList.remove('cell-selected'));
    selectedCells.clear();
    lastPickedCell=null;
    updateMultiBar();
}
function selectRange(fromTd,toTd){
    if(!fromTd||!toTd||fromTd.dataset.uid!==toTd.dataset.uid)return;
    const uid=fromTd.dataset.uid;
    const fromDay=parseInt(fromTd.dataset.day,10),toDay=parseInt(toTd.dataset.day,10);
    const min=Math.min(fromDay,toDay),max=Math.max(fromDay,toDay);
    for(let d=min;d<=max;d++){
        const c=document.querySelector(`.cell.ed[data-uid="${uid}"][data-day="${d}"]`);
        if(c)_toggleSelected(c,true);
    }
}
function updateMultiBar(){
    const bar=document.getElementById('multiBar');
    if(!bar)return;
    const count=selectedCells.size;
    document.getElementById('selCount').textContent=count;
    bar.style.display=count>=2?'':'none';
}
function sc(td,ev){
    const e=ev||window.event;
    const withCtrl=!!(e&&(e.ctrlKey||e.metaKey));
    const withShift=!!(e&&e.shiftKey);
    if(withShift&&lastPickedCell){
        clearSelection();
        selectRange(lastPickedCell,td);
        lastPickedCell=td;
        updateMultiBar();
        return;
    }
    if(withCtrl){
        _toggleSelected(td,!selectedCells.has(_cellKey(td)));
        lastPickedCell=td;
        updateMultiBar();
        return;
    }
    clearSelection();
    _toggleSelected(td,true);
    lastPickedCell=td;
    updateMultiBar();
}
function oe(td){
    document.getElementById('fUid').value=td.dataset.uid;
    document.getElementById('fDate').value=td.dataset.date;
    document.getElementById('fEid').value=td.dataset.eid||'';
    document.getElementById('ms').textContent='Dzień '+td.dataset.day;
    const t=td.dataset.type||'standard';
    document.getElementById('fType').value=t;
    const T=document.getElementById('fType'),o=T.options[T.selectedIndex];
    const nt=isNoTimeShift(t,(T.options[T.selectedIndex] ? T.options[T.selectedIndex].text : ''));
    document.getElementById('fS').value=nt?'':(td.dataset.start||o.dataset.s||'');
    document.getElementById('fE').value=nt?'':(td.dataset.end||o.dataset.e||'');
    document.getElementById('fN').value=td.dataset.note||'';
    document.getElementById('bd').style.display=td.dataset.eid?'':'none';
    updUI(t); calcH(); document.getElementById('mo').style.display='flex'; _ml();
}
function ce(e){if(e&&e.target!==e.currentTarget)return;document.getElementById('mo').style.display='none';_mu();}
function tc(){
    const T=document.getElementById('fType'),o=T.options[T.selectedIndex],t=T.value;
    const nt=isNoTimeShift(t,o.text||'');
    if(!nt){document.getElementById('fS').value=o.dataset.s||'';document.getElementById('fE').value=o.dataset.e||'';}
    else{document.getElementById('fS').value='';document.getElementById('fE').value='';}
    updUI(t); calcH();
}
function updUI(t){
    const F=document.getElementById('fType');
    const nt=isNoTimeShift(t,(F.options[F.selectedIndex] ? F.options[F.selectedIndex].text : ''));
    document.getElementById('timeRow').style.display=nt?'none':'';
    document.getElementById('hb').style.display='none';
    document.getElementById('dyzurHint').style.display=t==='dyzur'?'':'none';
    const nth=document.getElementById('noTimeHint');
    if(t==='urlop'){nth.textContent='Urlop — godziny pracy nie obowiązują';nth.style.display='';}
    else if(t==='urlop_na_zadanie'){nth.textContent='Urlop na żądanie — godziny pracy nie obowiązują';nth.style.display='';}
    else if(t==='chorobowe'){nth.textContent='Chorobowe — godziny pracy nie obowiązują';nth.style.display='';}
    else if(t==='swieto'){nth.textContent='Wpisz nazwę święta poniżej. Godziny pracy opcjonalne.';nth.style.display='';}
    else if(t==='wolne'){nth.textContent='Dzień wolny';nth.style.display='';}
    else if(t==='brak'){nth.textContent='Brak dyżuru';nth.style.display='';}
    else nth.style.display='none';
    const nl=document.getElementById('noteLbl');
    nl.textContent=t==='swieto'?'Nazwa święta':'Notatka';
    document.getElementById('fN').placeholder=t==='swieto'?'np. Dzień Niepodległości':'';
}
function calcH(){
    const s=document.getElementById('fS').value,e=document.getElementById('fE').value,d=document.getElementById('hb');
    if(s&&e){const[sh,sm]=s.split(':').map(Number),[eh,em]=e.split(':').map(Number),h=Math.max(0,((eh*60+em)-(sh*60+sm))/60).toFixed(1);document.getElementById('hv').textContent=h;d.style.display=h>0?'':'none';}
    else d.style.display='none';
}
function msTc(){
    const T=document.getElementById('msType'),o=T.options[T.selectedIndex],t=T.value;
    const nt=isNoTimeShift(t,o.text||'');
    document.getElementById('msS').value=nt?'':(o.dataset.s||'');
    document.getElementById('msE').value=nt?'':(o.dataset.e||'');
}
function applyMulti(){
    if(selectedCells.size<2){alert('Zaznacz minimum 2 komórki');return;}
    const cells=[];
    selectedCells.forEach(td=>cells.push({employee_id:td.dataset.uid,date:td.dataset.date}));
    const b=new URLSearchParams({
        _token:TK,
        action:'selected_cells',
        cells:JSON.stringify(cells),
        shift_type:document.getElementById('msType').value,
        shift_start:document.getElementById('msS').value,
        shift_end:document.getElementById('msE').value,
        note:document.getElementById('msN').value
    });
    fetch('api/bulk_edit.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(!d.ok){alert(d.error||'Błąd');return;}
        (d.updated||[]).forEach(it=>{
            const td=document.querySelector(`.cell.ed[data-uid="${it.user_id}"][data-date="${it.entry_date}"]`);
            if(!td)return;
            td.dataset.type=it.shift_type||'';
            td.dataset.start=it.shift_start||'';
            td.dataset.end=it.shift_end||'';
            td.dataset.note=it.note||'';
            td.dataset.eid=it.id||'';
            td.style.background=it.color;
            td.style.color=it.text;
            td.title=it.note||'';
            td.classList.toggle('cell-dyzur',it.shift_type==='dyzur');
            td.classList.toggle('cell-wrap',(it.label||'').length>6);
            td.querySelector('.cl').textContent=it.label||'';
            const h=td.querySelector('.ch');
            if(it.hours>0){if(h)h.textContent=it.hours+'h';else{const n=document.createElement('div');n.className='ch';n.textContent=it.hours+'h';td.appendChild(n);}}
            else if(h)h.remove();
        });
        clearSelection();
        alert('Zaktualizowano '+d.count+' komórek');
    }).catch(()=>alert('Błąd połączenia'));
}
function se(){
    const b=new URLSearchParams({_token:TK,user_id:document.getElementById('fUid').value,entry_date:document.getElementById('fDate').value,shift_type:document.getElementById('fType').value,shift_start:document.getElementById('fS').value,shift_end:document.getElementById('fE').value,note:document.getElementById('fN').value});
    fetch('api/save_entry.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){
            if(d.rest_warnings && d.rest_warnings.length>0){
                var msg='\u26a0\ufe0f UWAGA \u2014 Naruszenie 11h odpoczynku (art. 132 KP):\n\n';
                d.rest_warnings.forEach(function(w){msg+='\u2022 '+w+'\n';});
                msg+='\nZmiana zostala zapisana. Zalecana korekta harmonogramu.';
                alert(msg);
            }
            location.reload();
        } else alert(d.error||'B\u0142\u0105d');
    }).catch(()=>alert('B\u0142\u0105d po\u0142\u0105czenia'));
}
function de(){
    const id=document.getElementById('fEid').value;
    if(!id||!confirm('Usunąć ten wpis?'))return;
    fetch('api/delete_entry.php',{method:'POST',body:new URLSearchParams({_token:TK,id:id})}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.error);}).catch(()=>alert('Błąd'));
}
if(document.getElementById('msType'))msTc();

/* ── BULK EDIT ── */
function openBulk(uid,name){
    document.getElementById('bUid').value=uid;
    document.getElementById('bName').textContent=name;
    document.getElementById('bMonth').textContent='<?=MONTH_NAMES_PL[$month]?> <?=$year?>';
    const T=document.getElementById('bType'),o=T.options[T.selectedIndex];
    document.getElementById('bS').value=o.dataset.s||'';
    document.getElementById('bE').value=o.dataset.e||'';
    const p=document.getElementById('bPattern');
    if(p){p.value='sck';bPatternChanged();}
    bCalcH();
    document.getElementById('bmo').style.display='flex'; _ml();
}
function closeBulk(e){if(e&&e.target!==e.currentTarget)return;document.getElementById('bmo').style.display='none';_mu();}
function bTc(){
    const T=document.getElementById('bType'),o=T.options[T.selectedIndex];
    document.getElementById('bS').value=o.dataset.s||'';
    document.getElementById('bE').value=o.dataset.e||'';
    bCalcH();
}
function bCalcH(){
    const s=document.getElementById('bS').value,e=document.getElementById('bE').value,d=document.getElementById('bhb');
    if(s&&e){const[sh,sm]=s.split(':').map(Number),[eh,em]=e.split(':').map(Number),h=Math.max(0,((eh*60+em)-(sh*60+sm))/60).toFixed(1);document.getElementById('bhv').textContent=h;d.style.display=h>0?'':'none';}
    else d.style.display='none';
}
function bPatternChanged(){
    const isSck=document.getElementById('bPattern').value==='sck';
    document.getElementById('bS').disabled=isSck;
    document.getElementById('bE').disabled=isSck;
    const n=document.getElementById('bPatternNote');
    if(n)n.style.display=isSck?'block':'none';
}
function bulkSave(){
    const mode=document.getElementById('bMode').value;
    const modeLabels={weekdays:'dni robocze',all:'wszystkie dni',empty_only:'puste dni'};
    if(!confirm('Ustaw zmianę na '+modeLabels[mode]+' miesiąca dla tego pracownika?\nTa operacja nadpisze istniejące wpisy'+(mode==='empty_only'?' (tylko puste dni).':'.')))return;
    const b=new URLSearchParams({
        _token:TK,
        user_id:document.getElementById('bUid').value,
        year:CUR_Y,month:CUR_M,
        shift_type:document.getElementById('bType').value,
        shift_start:document.getElementById('bS').value,
        shift_end:document.getElementById('bE').value,
        note:document.getElementById('bN').value,
        mode:mode,
        work_pattern:document.getElementById('bPattern').value
    });
    fetch('api/bulk_edit.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Zaktualizowano '+d.count+' dni');location.reload();}
        else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}

/* ── COPY MONTH ── */
function openCopy(){document.getElementById('cmo').style.display='flex';_ml();}
function closeCopy(e){if(e&&e.target!==e.currentTarget)return;document.getElementById('cmo').style.display='none';_mu();}
function doCopy(){
    const dy=parseInt(document.getElementById('cDstY').value),dm=parseInt(document.getElementById('cDstM').value);
    const scope=document.getElementById('cScope').value;
    const ow=document.getElementById('cOw').checked?'1':'0';
    if(dy===CUR_Y&&dm===CUR_M){alert('Miesiąc docelowy musi być inny niż bieżący');return;}
    const scopeLabel=scope==='all'?'wszystkich pracowników':'wybranego pracownika';
    if(!confirm('Skopiować harmonogram z <?=MONTH_NAMES_PL[$month]?> <?=$year?> na '+document.getElementById('cDstM').options[document.getElementById('cDstM').selectedIndex].text+' '+dy+' dla '+scopeLabel+'?'))return;
    const b=new URLSearchParams({
        _token:TK,src_year:CUR_Y,src_month:CUR_M,
        dst_year:dy,dst_month:dm,
        scope:scope,overwrite:ow
    });
    fetch('api/copy_month.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Skopiowano '+d.count+' wpisów');window.location='?y='+dy+'&m='+dm;}
        else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}

/* ── SHIFT TYPES MANAGER ── */
let stData=<?=json_encode(array_values(array_map(function($c,$s){return['code'=>$c,'label'=>$s['label'],'color'=>$s['color'],'text'=>$s['text'],'start'=>$s['start']??'','end'=>$s['end']??''];},array_keys($shiftTypes),$shiftTypes)))?>;
const PROTECTED_ST=['standard','wolne','brak'];

function openShiftMgr(){renderStList();document.getElementById('stmo').style.display='flex';_ml();}
function closeStMgr(e){if(e&&e.target!==e.currentTarget)return;document.getElementById('stmo').style.display='none';_mu();}
function renderStList(){
    const c=document.getElementById('stList');
    c.innerHTML='';
    stData.forEach(function(s,i){
        const row=document.createElement('div');
        row.className='st-row';
        row.style.borderLeft='4px solid '+s.color;
        row.innerHTML='<div class="st-swatch" style="background:'+s.color+';color:'+s.text+'">'+s.label.substring(0,3)+'</div>'
            +'<div class="st-info"><strong>'+s.label+'</strong><div class="st-code">'+s.code+(s.start&&s.end?' · '+s.start+'-'+s.end:'')+'</div></div>';
        const editBtn=document.createElement('button');
        editBtn.className='btn btn-ghost btn-xs';
        editBtn.textContent='✏️';
        editBtn.onclick=function(){editSt(i);};
        row.appendChild(editBtn);
        if(!PROTECTED_ST.includes(s.code)){
            const delBtn=document.createElement('button');
            delBtn.className='btn btn-ghost btn-xs st-del';
            delBtn.textContent='🗑';
            delBtn.onclick=function(){delSt(s.code);};
            row.appendChild(delBtn);
        }
        c.appendChild(row);
    });
}
function stNoTime(code,label){
    return isNoTimeShift(code,label);
}
function updateStTimeVisibility(){
    const code=document.getElementById('stCode').value.trim().toLowerCase();
    const label=document.getElementById('stLabel').value.trim();
    const nt=stNoTime(code,label);
    const w=document.getElementById('stTimeWrap');
    const h=document.getElementById('stNoTimeHint');
    if(w)w.style.display=nt?'none':'';
    if(h)h.style.display=nt?'':'none';
    if(nt){document.getElementById('stStart').value='';document.getElementById('stEnd').value='';}
}
function editSt(i){
    const s=i>=0?stData[i]:{code:'',label:'',color:'#ea580c',text:'#ffffff',start:'',end:''};
    document.getElementById('stCode').value=s.code;
    document.getElementById('stCode').readOnly=i>=0;
    document.getElementById('stLabel').value=s.label;
    document.getElementById('stColor').value=s.color;
    document.getElementById('stText').value=s.text;
    document.getElementById('stStart').value=s.start;
    document.getElementById('stEnd').value=s.end;
    document.getElementById('stOrder').value=i>=0?i:stData.length;
    document.getElementById('stFormTitle').textContent=i>=0?'Edytuj typ zmiany':'Nowy typ zmiany';
    document.getElementById('stForm').style.display='';
    document.getElementById('stPreview').style.background=s.color;
    document.getElementById('stPreview').style.color=s.text;
    document.getElementById('stPreview').textContent=s.label||'Podgląd';
    updateStTimeVisibility();
}
function updateStPreview(){
    updateStTimeVisibility();
    const p=document.getElementById('stPreview');
    p.style.background=document.getElementById('stColor').value;
    p.style.color=document.getElementById('stText').value;
    p.textContent=document.getElementById('stLabel').value||'Podgląd';
}
function saveSt(){
    const code=document.getElementById('stCode').value.trim().toLowerCase().replace(/[^a-z0-9_]/g,'');
    const label=document.getElementById('stLabel').value.trim();
    if(!code||!label){alert('Kod i nazwa są wymagane');return;}
    const nt=stNoTime(code,label);
    const b=new URLSearchParams({
        _token:TK,action:'save',code:code,label:label,
        color:document.getElementById('stColor').value,
        text_color:document.getElementById('stText').value,
        default_start:nt?'':document.getElementById('stStart').value,
        default_end:nt?'':document.getElementById('stEnd').value,
        sort_order:document.getElementById('stOrder').value
    });
    fetch('api/shift_types.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){
            const idx=stData.findIndex(s=>s.code===code);
            const obj={code,label,color:document.getElementById('stColor').value,text:document.getElementById('stText').value,start:nt?'':document.getElementById('stStart').value,end:nt?'':document.getElementById('stEnd').value};
            if(idx>=0)stData[idx]=obj;else stData.push(obj);
            renderStList();document.getElementById('stForm').style.display='none';
            alert('Zapisano typ zmiany: '+label);location.reload();
        }else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}
function delSt(code){
    if(!confirm('Usunąć typ zmiany "'+code+'"?'))return;
    fetch('api/shift_types.php',{method:'POST',body:new URLSearchParams({_token:TK,action:'delete',code:code})}).then(r=>r.json()).then(d=>{
        if(d.ok){stData=stData.filter(s=>s.code!==code);renderStList();alert('Usunięto');location.reload();}
        else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}

/* ── CLEAR MONTH ── */
var _clrTmr=null;
function openClearMonth(){
    document.getElementById('clearMo').style.display='flex';_ml();
    var sec=6;
    var btn=document.getElementById('clearConfirmBtn');
    var timer=document.getElementById('clearTimer');
    var label=document.getElementById('clearTimerLabel');
    btn.disabled=true;btn.style.opacity='.4';btn.style.cursor='not-allowed';
    timer.textContent=sec;label.textContent=sec+' sekund';
    if(_clrTmr){window.clearInterval(_clrTmr);_clrTmr=null;}
    _clrTmr=window.setInterval(function(){
        sec--;
        if(sec>0){
            timer.textContent=sec;
            label.textContent=sec>1?(sec+' sekund'):'1 sekundę';
        }else{
            window.clearInterval(_clrTmr);_clrTmr=null;
            btn.disabled=false;btn.style.opacity='1';btn.style.cursor='pointer';
            timer.textContent='✓';
            label.textContent='Możesz teraz potwierdzić';
        }
    },1000);
}
function closeClearMonth(e){
    if(e&&e.target!==e.currentTarget)return;
    document.getElementById('clearMo').style.display='none';_mu();
    if(_clrTmr){window.clearInterval(_clrTmr);_clrTmr=null;}
}
function doClearMonth(){
    fetch('api/clear_month.php',{method:'POST',body:new URLSearchParams({_token:TK,year:CUR_Y,month:CUR_M})}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Usunięto '+d.count+' wpisów z harmonogramu');location.reload();}
        else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}

/* ── AUTOFILL ── */
function openAutoFill(){
    document.getElementById('afMo').style.display='flex';_ml();
    updateAfPreview();
}
function closeAutoFill(e){
    if(e&&e.target!==e.currentTarget)return;
    document.getElementById('afMo').style.display='none';_mu();
}
function getAfDepts(){
    return Array.from(document.querySelectorAll('.af-dept-check input:checked')).map(function(c){return c.value;});
}
function getAfMode(){
    return document.querySelector('input[name="af_mode"]:checked').value;
}
function updateAfPreview(){
    var depts=getAfDepts();
    var el=document.getElementById('afPreview');
    if(depts.length===0){el.textContent='Wybierz przynajmniej jeden dział.';return;}
    el.textContent='Uzupełnienie dla: '+depts.join(', ')+' ('+getAfMode()+')';
}
document.addEventListener('change',function(e){
    if(e.target.closest('.af-dept-check') || e.target.closest('.af-radio'))updateAfPreview();
});
function doAutoFill(){
    var depts=getAfDepts();
    if(depts.length===0){alert('Wybierz przynajmniej jeden dział.');return;}
    var mode=getAfMode();
    var msg='Uzupełnić harmonogram standardowymi godzinami pracy';
    msg+='\ndla działów: '+depts.join(', ')+'?';
    if(mode==='overwrite') msg+='\n\nUWAGA: Tryb nadpisywania — istniejące wpisy zostaną zastąpione!';
    if(!confirm(msg))return;
    var body=new URLSearchParams({_token:TK,year:CUR_Y,month:CUR_M,mode:mode});
    depts.forEach(function(d){body.append('depts[]',d);});
    fetch('api/autofill.php',{method:'POST',body:body}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Uzupełniono '+d.count+' komórek harmonogramu.');location.reload();}
        else alert(d.error||'Błąd');
    }).catch(()=>alert('Błąd połączenia'));
}
</script>

<!-- BULK EDIT MODAL -->
<div class="overlay" id="bmo" style="display:none" onclick="closeBulk(event)">
<div class="modal modal-bulk" onclick="event.stopPropagation()">
    <div class="mh"><div><h3>⏱ Edycja godzin na cały miesiąc</h3><div class="ms"><span id="bName"></span> — <span id="bMonth"></span></div></div><button class="mc" onclick="closeBulk()">×</button></div>
    <div class="mb">
        <div class="bulk-info">Ustaw jednakową zmianę dla wybranych dni miesiąca. Idealne gdy pracownik ma stałe godziny pracy.</div>
        <label class="lbl">Typ zmiany</label>
        <select id="bType" class="input" onchange="bTc()">
            <?php foreach($shiftTypes as $c=>$s): ?>
            <option value="<?=$c?>" data-s="<?=h($s['start'])?>" data-e="<?=h($s['end'])?>"><?=h($s['label'])?><?=$s['start']&&$s['end']?" ({$s['start']}-{$s['end']})":''?></option>
            <?php endforeach; ?>
        </select>
        <label class="lbl">Szablon czasu pracy</label>
        <select id="bPattern" class="input" onchange="bPatternChanged()">
            <option value="sck">Domyślne godziny SCK (pn-czw 7:30-16:00, pt 7:30-13:30)</option>
            <option value="custom">Własne godziny (ręcznie ustaw start/koniec)</option>
        </select>
        <div id="bPatternNote" class="bulk-info" style="margin-bottom:8px">Dla szablonu SCK godziny są ustawiane automatycznie dla każdego dnia roboczego.</div>
        <div class="g2">
            <div><label class="lbl">Godzina od</label><input type="time" id="bS" class="input" oninput="bCalcH()"></div>
            <div><label class="lbl">Godzina do</label><input type="time" id="bE" class="input" oninput="bCalcH()"></div>
        </div>
        <div class="hrs-box" id="bhb" style="display:none">⏱ <strong id="bhv">0</strong> godzin dziennie</div>
        <label class="lbl">Zakres dni</label>
        <select id="bMode" class="input">
            <option value="weekdays">Tylko dni robocze (pn-pt)</option>
            <option value="all">Wszystkie dni (pn-nd)</option>
            <option value="empty_only">Tylko puste dni (nie nadpisuj istniejących)</option>
        </select>
        <label class="lbl">Notatka (opcjonalna)</label>
        <textarea id="bN" class="input" rows="2"></textarea>
        <input type="hidden" id="bUid">
    </div>
    <div class="mf">
        <button class="btn btn-primary" onclick="bulkSave()">✓ Zastosuj na cały miesiąc</button>
        <button class="btn btn-ghost" onclick="closeBulk()">Anuluj</button>
    </div>
</div>
</div>

<!-- COPY MONTH MODAL -->
<div class="overlay" id="cmo" style="display:none" onclick="closeCopy(event)">
<div class="modal" onclick="event.stopPropagation()">
    <div class="mh"><div><h3>📋 Kopiuj harmonogram</h3><div class="ms">Z: <?=MONTH_NAMES_PL[$month]?> <?=$year?></div></div><button class="mc" onclick="closeCopy()">×</button></div>
    <div class="mb">
        <label class="lbl">Kopiuj na miesiąc</label>
        <div class="g2">
            <div>
                <select id="cDstM" class="input">
                    <?php foreach(MONTH_NAMES_PL as $mi=>$mn): ?>
                    <option value="<?=$mi?>" <?=$mi===$nextM?'selected':''?>><?=h($mn)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <select id="cDstY" class="input">
                    <?php for($yi=$year-1;$yi<=$year+2;$yi++): ?>
                    <option value="<?=$yi?>" <?=$yi===($nextM===1?$nextY:$year)?'selected':''?>><?=$yi?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <label class="lbl">Zakres</label>
        <select id="cScope" class="input">
            <option value="all">Wszyscy pracownicy</option>
            <?php foreach($allEmps as $e): ?>
            <option value="<?=$e['id']?>">Tylko: <?=h($e['full_name'])?></option>
            <?php endforeach; ?>
        </select>
        <label class="lbl" style="margin-top:8px">
            <input type="checkbox" id="cOw"> Nadpisz istniejące wpisy w miesiącu docelowym
        </label>
        <div class="copy-warn">Wpisy, których dzień nie istnieje w miesiącu docelowym (np. 31. dzień), zostaną pominięte.</div>
    </div>
    <div class="mf">
        <button class="btn btn-primary" onclick="doCopy()">📋 Kopiuj harmonogram</button>
        <button class="btn btn-ghost" onclick="closeCopy()">Anuluj</button>
    </div>
</div>
</div>

<!-- SHIFT TYPES MANAGER MODAL -->
<div class="overlay" id="stmo" style="display:none" onclick="closeStMgr(event)">
<div class="modal modal-wide" onclick="event.stopPropagation()">
    <div class="mh"><div><h3>🎨 Zarządzanie typami zmian</h3><div class="ms">Dodawaj, edytuj kolory i nazwy typów zmian</div></div><button class="mc" onclick="closeStMgr()">×</button></div>
    <div class="mb" style="max-height:60vh;overflow-y:auto">
        <div id="stList"></div>
        <button class="btn btn-primary" onclick="editSt(-1)" style="margin-top:12px;width:100%">+ Dodaj nowy typ zmiany</button>
        <div id="stForm" style="display:none;margin-top:16px;padding:16px;background:#fafaf9;border:1px solid #e7e5e4;border-radius:10px">
            <h4 id="stFormTitle" style="font-size:13px;font-weight:700;margin-bottom:12px">Nowy typ zmiany</h4>
            <div class="g2">
                <div><label class="lbl">Kod (unikalne ID)</label><input type="text" id="stCode" class="input" placeholder="np. nocna" pattern="[a-z0-9_]+" oninput="updateStTimeVisibility()"></div>
                <div><label class="lbl">Nazwa wyświetlana</label><input type="text" id="stLabel" class="input" placeholder="np. Zmiana nocna" oninput="updateStPreview()"></div>
            </div>
            <div class="g2">
                <div><label class="lbl">Kolor tła</label><div style="display:flex;gap:8px;align-items:center"><input type="color" id="stColor" value="#ea580c" style="width:48px;height:36px;border:1px solid #e7e5e4;border-radius:6px;cursor:pointer;padding:2px" oninput="updateStPreview()"><span class="st-hex" id="stColorHex"></span></div></div>
                <div><label class="lbl">Kolor tekstu</label><div style="display:flex;gap:8px;align-items:center"><input type="color" id="stText" value="#ffffff" style="width:48px;height:36px;border:1px solid #e7e5e4;border-radius:6px;cursor:pointer;padding:2px" oninput="updateStPreview()"><span class="st-hex" id="stTextHex"></span></div></div>
            </div>
            <label class="lbl">Podgląd</label>
            <div id="stPreview" style="padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;text-align:center;margin-bottom:12px;background:#ea580c;color:#fff">Podgląd</div>
            <div id="stNoTimeHint" class="bulk-info" style="display:none">Dla tego typu zmiany nie ustawiamy godzin.</div>
            <div class="g2" id="stTimeWrap">
                <div><label class="lbl">Domyślna godzina od</label><input type="time" id="stStart" class="input"></div>
                <div><label class="lbl">Domyślna godzina do</label><input type="time" id="stEnd" class="input"></div>
            </div>
            <input type="hidden" id="stOrder" value="0">
            <div style="display:flex;gap:8px;margin-top:8px">
                <button class="btn btn-primary" onclick="saveSt()">✓ Zapisz</button>
                <button class="btn btn-ghost" onclick="document.getElementById('stForm').style.display='none'">Anuluj</button>
            </div>
        </div>
    </div>
    <div class="mf">
        <button class="btn btn-ghost" onclick="closeStMgr()">Zamknij</button>
    </div>
</div>
</div>

<!-- CLEAR MONTH MODAL -->
<div class="overlay" id="clearMo" style="display:none" onclick="closeClearMonth(event)">
<div class="modal modal-bulk" onclick="event.stopPropagation()">
    <div class="mh"><div><h3 style="color:#dc2626">🗑 Wyczyść harmonogram</h3><div class="ms"><?=MONTH_NAMES_PL[$month]?> <?=$year?></div></div><button class="mc" onclick="closeClearMonth()">×</button></div>
    <div class="mb">
        <div class="alert alert-danger" style="margin:0 0 16px">
            <strong>⚠️ UWAGA — ta operacja jest nieodwracalna!</strong>
        </div>
        <div class="clear-warn-list">
            Ta funkcja usunie <strong>wszystkie wpisy harmonogramu</strong> za miesiąc <strong><?=MONTH_NAMES_PL[$month]?> <?=$year?></strong> dla <strong>wszystkich pracowników</strong>.<br><br>
            Dotyczy to zmian, urlopów, chorobowych, dyżurów i wszelkich innych wpisów w tym miesiącu.<br><br>
            <strong>Danych nie będzie można odzyskać.</strong>
        </div>
        <div class="clear-countdown" id="clearTimer">6</div>
        <div style="text-align:center;font-size:11px;color:#a8a29e;margin-bottom:12px">Poczekaj <span id="clearTimerLabel">6 sekund</span> przed potwierdzeniem</div>
    </div>
    <div class="mf">
        <button class="btn btn-danger" id="clearConfirmBtn" onclick="doClearMonth()" disabled style="opacity:.4;cursor:not-allowed">🗑 Wyczyść cały miesiąc</button>
        <button class="btn btn-ghost" onclick="closeClearMonth()">Anuluj</button>
    </div>
</div>
</div>

<!-- AUTOFILL MODAL -->
<div class="overlay" id="afMo" style="display:none" onclick="closeAutoFill(event)">
<div class="modal" onclick="event.stopPropagation()" style="max-width:520px">
    <div class="mh"><div><h3>⚡ Autouzupełnianie harmonogramu</h3><div class="ms"><?=MONTH_NAMES_PL[$month]?> <?=$year?></div></div><button class="mc" onclick="closeAutoFill()">×</button></div>
    <div class="mb">
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#9a3412;line-height:1.5">
            <strong>Standardowe godziny pracy:</strong><br>
            Poniedziałek – Czwartek: <strong>7:30 – 16:00</strong> (8.5h)<br>
            Piątek: <strong>7:30 – 13:30</strong> (6h)
        </div>
        <div style="margin-bottom:12px">
            <label style="font-size:12px;font-weight:700;color:#1c1917;display:block;margin-bottom:8px">Wybierz działy do uzupełnienia:</label>
            <?php foreach($allDepts as $dept): ?>
            <label class="af-dept-check">
                <input type="checkbox" value="<?=h($dept)?>" checked>
                <span class="af-dept-name"><?=h($dept)?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <div style="margin-bottom:12px">
            <label style="font-size:12px;font-weight:700;color:#1c1917;display:block;margin-bottom:8px">Tryb uzupełniania:</label>
            <label class="af-radio"><input type="radio" name="af_mode" value="empty" checked> Tylko puste dni <span style="font-size:10px;color:#78716c">(nie nadpisuje istniejących wpisów)</span></label>
            <label class="af-radio"><input type="radio" name="af_mode" value="overwrite"> Nadpisz wszystkie dni robocze <span style="font-size:10px;color:#dc2626">(uwaga: nadpisze urlopy, dyżury itp.)</span></label>
        </div>
        <div id="afPreview" style="font-size:11px;color:#78716c;padding:8px 0"></div>
    </div>
    <div class="mf">
        <button class="btn btn-primary" onclick="doAutoFill()">⚡ Uzupełnij harmonogram</button>
        <button class="btn btn-ghost" onclick="closeAutoFill()">Anuluj</button>
    </div>
</div>
</div>

<?php endif; ?>

<!-- Drag-scroll for ALL users -->
<script>
(function(){
    const el=document.getElementById('sw');
    let on=0,sx=0,sl=0,mv=0;
    el.addEventListener('mousedown',e=>{if(e.button!==0)return;on=1;mv=0;sx=e.pageX;sl=el.scrollLeft;el.style.cursor='grabbing';el.style.userSelect='none';});
    document.addEventListener('mousemove',e=>{if(!on)return;const dx=e.pageX-sx;if(Math.abs(dx)>4)mv=1;el.scrollLeft=sl-dx;});
    document.addEventListener('mouseup',()=>{on=0;el.style.cursor='grab';el.style.userSelect='';});
    el.addEventListener('click',e=>{if(mv){e.stopPropagation();mv=0;}},true);
})();
</script>

<?php layout_end(); ?>
