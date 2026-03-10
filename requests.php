<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
if (!is_admin()) { header('Location: schedule.php'); exit; }

$db = get_db();

// Cleanup expired PDFs
cleanup_expired_requests();

// Get requests where this admin is an approver
$pending = $db->prepare("
    SELECT fr.*, u.full_name AS employee_name, u.email AS employee_email
    FROM form_requests fr
    JOIN users u ON u.id = fr.user_id
    JOIN form_approvers fa ON fa.employee_id = fr.user_id AND fa.approver_id = ?
    WHERE fr.status = 'pending'
    ORDER BY fr.created_at DESC
");
$pending->execute([$user['id']]);
$pendingReqs = $pending->fetchAll();

// Get recent decided (for history)
$history = $db->prepare("
    SELECT fr.*, u.full_name AS employee_name, d.full_name AS decided_by_name
    FROM form_requests fr
    JOIN users u ON u.id = fr.user_id
    LEFT JOIN users d ON d.id = fr.decided_by
    WHERE fr.status != 'pending'
    ORDER BY fr.decided_at DESC
    LIMIT 30
");
$history->execute();
$historyReqs = $history->fetchAll();

$formLabels = [
    'leave' => 'Wniosek o urlop',
    'overtime' => 'Czas wolny za nadgodziny',
    'wifi' => 'Oświadczenie Wi-Fi'
];

layout_start('Wnioski pracowników');
?>

<style>
.rq-wrap{max-width:900px;margin:0 auto;padding:16px 24px}
.rq-header{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.rq-hicon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.rq-hicon svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rq-htitle{font-size:24px;font-weight:800;color:#1c1917;letter-spacing:-.02em}
.rq-count{font-size:12px;font-weight:700;background:linear-gradient(135deg,#ea580c,#f97316);color:#fff;padding:3px 10px;border-radius:99px}

.rq-section{font-size:13px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em;margin:20px 0 10px;display:flex;align-items:center;gap:8px}
.rq-section::after{content:'';flex:1;height:1px;background:#f5f5f4}

.rq-card{background:#fff;border:1.5px solid #f5f5f4;border-radius:16px;padding:18px 22px;margin-bottom:10px;transition:border-color .2s,box-shadow .2s}
.rq-card:hover{border-color:#e7e5e4;box-shadow:0 4px 16px rgba(0,0,0,.04)}
.rq-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap}
.rq-card-info{flex:1;min-width:0}
.rq-card-name{font-size:15px;font-weight:800;color:#1c1917}
.rq-card-type{font-size:12px;color:#78716c;margin-top:2px}
.rq-card-date{font-size:11px;color:#a8a29e}
.rq-badge{font-size:10px;font-weight:700;padding:3px 10px;border-radius:99px;flex-shrink:0}
.rq-badge-pending{background:#fff7ed;color:#ea580c;border:1px solid #fed7aa}
.rq-badge-approved{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.rq-badge-rejected{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

.rq-data{background:#fafaf9;border-radius:10px;padding:12px 16px;font-size:12px;color:#57534e;line-height:1.6;margin-bottom:12px}
.rq-data strong{color:#1c1917}

.rq-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.rq-reject-reason{flex:1;min-width:200px;padding:8px 12px;border:1.5px solid #e7e5e4;border-radius:8px;font-size:12px;font-family:inherit}
.rq-reject-reason:focus{border-color:#ea580c;outline:none}

.rq-decided{font-size:11px;color:#a8a29e;margin-top:6px}
.rq-decided strong{color:#57534e}
.rq-reason{background:#fef2f2;border-radius:8px;padding:8px 12px;font-size:11px;color:#991b1b;margin-top:6px}

.rq-empty{text-align:center;padding:48px 24px;color:#a8a29e}
.rq-empty-icon{width:56px;height:56px;border-radius:16px;background:#f5f5f4;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.rq-empty-icon svg{width:24px;height:24px;stroke:#d6d3d1}
</style>

<div class="rq-wrap">
    <div class="rq-header">
        <div class="rq-hicon">
            <svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
        </div>
        <div class="rq-htitle">Wnioski pracownik&oacute;w</div>
        <?php if(count($pendingReqs)>0):?>
        <span class="rq-count"><?=count($pendingReqs)?> oczekuj&#261;cych</span>
        <?php endif;?>
    </div>

    <?php if(empty($pendingReqs) && empty($historyReqs)):?>
    <div class="rq-empty">
        <div class="rq-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/></svg></div>
        <div style="font-size:15px;font-weight:700">Brak wniosk&oacute;w</div>
        <div style="font-size:12px;margin-top:4px">Gdy pracownik wy&#347;le formularz, pojawi si&#281; tutaj do akceptacji</div>
    </div>
    <?php endif;?>

    <?php if(!empty($pendingReqs)):?>
    <div class="rq-section">Oczekuj&#261;ce na decyzj&#281; (<?=count($pendingReqs)?>)</div>
    <?php foreach($pendingReqs as $r):
        $data = json_decode($r['form_data'], true) ?: [];
        $label = $formLabels[$r['form_type']] ?? $r['form_type'];
    ?>
    <div class="rq-card" id="rq-<?=$r['id']?>">
        <div class="rq-card-head">
            <div class="rq-card-info">
                <div class="rq-card-name"><?=h($r['employee_name'])?></div>
                <div class="rq-card-type"><?=h($label)?></div>
                <div class="rq-card-date">Z&#322;o&#380;ono: <?=date('d.m.Y H:i', strtotime($r['created_at']))?></div>
            </div>
            <span class="rq-badge rq-badge-pending">Oczekuje</span>
        </div>
        <div class="rq-data">
            <?php foreach($data as $k => $v): if($v):?>
            <div><strong><?=h($k)?>:</strong> <?=h($v)?></div>
            <?php endif; endforeach;?>
        </div>
        <div class="rq-actions">
            <button class="btn btn-primary" onclick="approveReq(<?=$r['id']?>)" style="font-size:12px;padding:8px 16px">
                &#10003; Akceptuj
            </button>
            <input class="rq-reject-reason" id="reason-<?=$r['id']?>" placeholder="Pow&oacute;d odmowy...">
            <button class="btn btn-ghost btn-danger-ghost" onclick="rejectReq(<?=$r['id']?>)" style="font-size:12px;padding:8px 16px">
                &#10007; Odrzu&#263;
            </button>
        </div>
    </div>
    <?php endforeach;?>
    <?php endif;?>

    <?php if(!empty($historyReqs)):?>
    <div class="rq-section">Historia (ostatnie 30)</div>
    <?php foreach($historyReqs as $r):
        $data = json_decode($r['form_data'], true) ?: [];
        $label = $formLabels[$r['form_type']] ?? $r['form_type'];
        $isApproved = $r['status'] === 'approved';
    ?>
    <div class="rq-card" style="opacity:.75">
        <div class="rq-card-head">
            <div class="rq-card-info">
                <div class="rq-card-name"><?=h($r['employee_name'])?></div>
                <div class="rq-card-type"><?=h($label)?></div>
                <div class="rq-card-date"><?=date('d.m.Y H:i', strtotime($r['created_at']))?></div>
            </div>
            <span class="rq-badge <?=$isApproved?'rq-badge-approved':'rq-badge-rejected'?>"><?=$isApproved?'Zaakceptowany':'Odrzucony'?></span>
        </div>
        <div class="rq-decided">
            <?=$isApproved?'Zaakceptowano':'Odrzucono'?> przez <strong><?=h($r['decided_by_name']??'?')?></strong>
            dnia <?=date('d.m.Y H:i', strtotime($r['decided_at']))?>
            <?php if($isApproved && $r['pdf_file']):?>
            <a href="api/download_request.php?id=<?=$r['id']?>" target="_blank" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;text-decoration:none;margin-left:8px">📄 Pobierz PDF</a>
            <?php endif;?>
            <?php if($r['expires_at']):?><span style="font-size:9px;color:#d6d3d1;margin-left:6px">wygasa: <?=date('d.m.Y',strtotime($r['expires_at']))?></span><?php endif;?>
        </div>
        <?php if(!$isApproved && $r['reject_reason']):?>
        <div class="rq-reason">Pow&oacute;d: <?=h($r['reject_reason'])?></div>
        <?php endif;?>
    </div>
    <?php endforeach;?>
    <?php endif;?>
</div>

<script>
var TK='<?=h($_SESSION['csrf']??'')?>';
function approveReq(id){
    if(!confirm('Zaakceptowa\u0107 ten wniosek?'))return;
    var b=new URLSearchParams({_token:TK,action:'approve',request_id:id});
    fetch('api/form_request.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Wniosek zaakceptowany.');location.reload();}
        else alert(d.error||'B\u0142\u0105d');
    }).catch(()=>alert('B\u0142\u0105d po\u0142\u0105czenia'));
}
function rejectReq(id){
    var reason=document.getElementById('reason-'+id).value.trim();
    if(!reason){alert('Wpisz pow\u00f3d odmowy.');document.getElementById('reason-'+id).focus();return;}
    if(!confirm('Odrzuci\u0107 ten wniosek?'))return;
    var b=new URLSearchParams({_token:TK,action:'reject',request_id:id,reason:reason});
    fetch('api/form_request.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok){alert('Wniosek odrzucony.');location.reload();}
        else alert(d.error||'B\u0142\u0105d');
    }).catch(()=>alert('B\u0142\u0105d po\u0142\u0105czenia'));
}
</script>

<?php layout_end(); ?>
