<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
ensure_users_profile_columns();
$formsVisible = get_setting('forms_visible', '1') === '1';
if (!$formsVisible && !is_admin()) { header('Location: schedule.php'); exit; }

// Fetch leave balances from DB
$currentYear = (int)date('Y');
$myLeave = calc_leave_remaining($user['id'], $currentYear);
$leavePrev = $myLeave['prev_remain'];
$leaveCurr = $myLeave['curr_remain'];
$leavePrevPool = $myLeave['prev_pool'];
$leaveCurrPool = $myLeave['curr_pool'];
$leaveTotal = $myLeave['total'];
$usedDays = $myLeave['used'];
$leaveRemain = $myLeave['remain'];

layout_start('Wniosek urlopowy');
?>

<style>
.fwrap{max-width:800px;margin:0 auto;padding:16px 24px}
.fheader{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.fheader-left{display:flex;align-items:center;gap:12px}
.fhicon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.fhicon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.fhtitle{font-size:18px;font-weight:800;color:#1c1917}
.fback{font-size:12px;color:#78716c;text-decoration:none;display:flex;align-items:center;gap:4px}
.fback:hover{color:#ea580c}
.fback svg{width:14px;height:14px}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px;margin-bottom:18px}
@media(max-width:560px){.fg{grid-template-columns:1fr}}
.fg-label{font-size:12px;font-weight:700;color:#1c1917;margin-bottom:4px;display:block}
.fg-input{width:100%;padding:10px 14px;border:1.5px solid #e7e5e4;border-radius:10px;font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;color:#1c1917;box-sizing:border-box;background:#fff}
.fg-input:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);outline:none}
.factions{display:flex;gap:10px;margin:16px 0;flex-wrap:wrap}
/* Leave info panel */
.leave-info{background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1.5px solid #fed7aa;border-radius:14px;padding:16px 20px;margin-bottom:18px;display:flex;gap:20px;flex-wrap:wrap;align-items:center}
.leave-info-item{display:flex;flex-direction:column}
.leave-info-label{font-size:10px;font-weight:700;color:#9a3412;text-transform:uppercase;letter-spacing:.03em}
.leave-info-val{font-size:20px;font-weight:900;color:#ea580c;letter-spacing:-.02em}
.leave-info-val small{font-size:12px;font-weight:600;color:#9a3412}
.leave-info-sep{width:1px;height:32px;background:#fdba74}
/* Preview */
.fpreview{background:#fff;border:1.5px solid #e7e5e4;border-radius:16px;overflow:hidden}
.fpreview-head{background:#fafaf9;padding:10px 20px;border-bottom:1px solid #f5f5f4}
.fpreview-label{font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em}
.fdoc{padding:32px;font-family:'Times New Roman',Times,serif;font-size:12px;line-height:1.8;color:#000}
.fdoc-field{border-bottom:1px dotted #999;display:inline-block;min-width:140px;padding:0 4px;font-weight:600;color:#1c1917}
.fdoc-field-sm{min-width:50px}
.fdoc-field-date{min-width:100px}
.fdoc-dots{letter-spacing:1px;color:#999}
.fdoc-label{font-size:10px;color:#666;text-align:center;font-style:italic}
.fdoc-sig{margin-top:48px;display:grid;grid-template-columns:1fr 1fr;gap:60px}
.fdoc-sig-item{text-align:center}
.fdoc-sig-dots{letter-spacing:1px;color:#999;margin-bottom:2px}
.fdoc-sig-label{font-size:10px;color:#666;font-style:italic}
.fdoc-to{text-align:right;font-weight:700;margin-bottom:20px;font-size:12px;line-height:1.6}
@media print{
    body *{visibility:hidden}
    .fpreview,.fpreview *{visibility:visible}
    .fpreview{position:absolute;left:0;top:0;width:100%;border:none;border-radius:0}
    .fpreview-head{display:none}
    .fdoc{padding:20mm;font-size:12pt;line-height:1.8}
}
</style>

<div class="fwrap">
    <div class="fheader">
        <div class="fheader-left">
            <div class="fhicon"><svg viewBox="0 0 24 24"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/></svg></div>
            <div class="fhtitle">Wniosek o urlop wypoczynkowy</div>
        </div>
        <a href="forms.php" class="fback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Powr&#243;t do listy
        </a>
    </div>

    <!-- Leave balance info -->
    <div class="leave-info">
        <div class="leave-info-item">
            <span class="leave-info-label">Urlop zaleg&#322;y (<?=$currentYear-1?>)</span>
            <span class="leave-info-val"><?=$leavePrev?> <small>z <?=$leavePrevPool?> dni</small></span>
        </div>
        <div class="leave-info-sep"></div>
        <div class="leave-info-item">
            <span class="leave-info-label">Urlop bie&#380;&#261;cy (<?=$currentYear?>)</span>
            <span class="leave-info-val"><?=$leaveCurr?> <small>z <?=$leaveCurrPool?> dni</small></span>
        </div>
        <div class="leave-info-sep"></div>
        <div class="leave-info-item">
            <span class="leave-info-label">Wykorzystano</span>
            <span class="leave-info-val"><?=$usedDays?> <small>dni</small></span>
        </div>
        <div class="leave-info-sep"></div>
        <div class="leave-info-item">
            <span class="leave-info-label">Pozosta&#322;o do wykorzystania</span>
            <span class="leave-info-val"><?=$leaveRemain?> <small>dni</small></span>
        </div>
    </div>

    <!-- Form fields -->
    <div class="fg">
        <div>
            <label class="fg-label">Imi&#281; i nazwisko</label>
            <input class="fg-input" type="text" id="ulName" value="<?=h($user['full_name'])?>" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Stanowisko służbowe</label>
            <input class="fg-input" type="text" id="ulPosition" value="<?=h($user['job_title'] ?? '')?>" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Data wniosku</label>
            <input class="fg-input" type="date" id="ulDate" value="<?=date('Y-m-d')?>" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Urlop przys&#322;uguj&#261;cy za rok</label>
            <input class="fg-input" type="number" id="ulYear" value="<?=date('Y')?>" min="2020" max="2099" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Liczba dni urlopu</label>
            <input class="fg-input" type="number" id="ulDays" value="" min="1" max="60" placeholder="np. 5" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Termin od</label>
            <input class="fg-input" type="date" id="ulFrom" oninput="upd()">
        </div>
        <div>
            <label class="fg-label">Termin do</label>
            <input class="fg-input" type="date" id="ulTo" oninput="upd()">
        </div>
    </div>

    <div class="factions">
        <button class="btn btn-primary" onclick="doPrint()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Drukuj / Zapisz PDF
        </button>
        <?php if(can_submit_form($user['id'])):?>
        <button class="btn btn-ghost" onclick="sendForm()" style="border-color:#ea580c;color:#ea580c">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Wy&#347;lij do akceptacji
        </button>
        <?php endif;?>
    </div>

    <!-- Document preview -->
    <div class="fpreview" id="printArea">
        <div class="fpreview-head">
            <span class="fpreview-label">Podgl&#261;d dokumentu</span>
        </div>
        <div class="fdoc">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px">
                <div style="width:45%">
                    <div style="text-align:center;margin:20px 0 6px">
                        <div class="fdoc-field" id="docName" style="min-width:200px"><?=h($user['full_name'])?></div>
                    </div>
                    <div class="fdoc-label">/Imi&#281; i nazwisko/</div>

                    <div style="text-align:center;margin:20px 0 6px">
                        <div class="fdoc-field fdoc-field-date" id="docDate"></div>
                    </div>
                    <div class="fdoc-label">/Data/</div>

                    <div style="text-align:center;margin:20px 0 6px">
                        <div class="fdoc-field" id="docPos" style="min-width:220px"><?=h($user['job_title'] ?? '')?></div>
                    </div>
                    <div class="fdoc-label">/Stanowisko s&#322;u&#380;bowe/</div>
                </div>
                <div class="fdoc-to">
                    DYREKTOR<br>
                    Strzegomskie Centrum Kultury
                </div>
            </div>

            <div style="text-align:center;font-size:14px;font-weight:700;margin:28px 0 20px">
                Wniosek o udzielenie urlopu wypoczynkowego
            </div>

            <p style="text-align:justify;text-indent:2em">
                Zwracam si&#281; z pro&#347;b&#261; o udzielenie mi urlopu wypoczynkowego przys&#322;uguj&#261;cego za rok
                <span class="fdoc-field fdoc-field-sm" id="docYear"><?=date('Y')?></span>,
                w liczbie
                <span class="fdoc-field fdoc-field-sm" id="docDays"></span>
                dni w terminie od
                <span class="fdoc-field fdoc-field-date" id="docFrom"></span>
                do
                <span class="fdoc-field fdoc-field-date" id="docTo"></span>.
            </p>

            <div class="fdoc-sig">
                <div class="fdoc-sig-item">
                    <div class="fdoc-dots">................................</div>
                    <div class="fdoc-sig-label">/Podpis pracownika/</div>
                </div>
                <div class="fdoc-sig-item">
                    <div class="fdoc-dots">................................</div>
                    <div class="fdoc-sig-label">/Podpis Dyrektora/</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fmtDate(v){if(!v)return '';var p=v.split('-');return p[2]+'.'+p[1]+'.'+p[0];}
function upd(){
    document.getElementById('docName').textContent=document.getElementById('ulName').value;
    document.getElementById('docDate').textContent=fmtDate(document.getElementById('ulDate').value);
    document.getElementById('docPos').textContent=document.getElementById('ulPosition').value;
    document.getElementById('docYear').textContent=document.getElementById('ulYear').value;
    document.getElementById('docDays').textContent=document.getElementById('ulDays').value;
    document.getElementById('docFrom').textContent=fmtDate(document.getElementById('ulFrom').value);
    document.getElementById('docTo').textContent=fmtDate(document.getElementById('ulTo').value);
}
function doPrint(){
    var n=document.getElementById('ulName').value.trim();
    if(!n){alert('Wype\u0142nij imi\u0119 i nazwisko.');document.getElementById('ulName').focus();return;}
    window.print();
}
function sendForm(){
    var n=document.getElementById('ulName').value.trim();
    if(!n){alert('Wype\u0142nij imi\u0119 i nazwisko.');return;}
    var data={
        'Imi\u0119 i nazwisko':n,
        'Data':fmtDate(document.getElementById('ulDate').value),
        'Stanowisko służbowe':document.getElementById('ulPosition').value,
        'Za rok':document.getElementById('ulYear').value,
        'Liczba dni':document.getElementById('ulDays').value,
        'Od':fmtDate(document.getElementById('ulFrom').value),
        'Do':fmtDate(document.getElementById('ulTo').value)
    };
    if(!confirm('Wys\u0142a\u0107 wniosek o urlop do akceptacji?'))return;
    var b=new URLSearchParams({_token:'<?=h($_SESSION['csrf']??'')?>',action:'submit',form_type:'leave',form_data:JSON.stringify(data)});
    fetch('api/form_request.php',{method:'POST',body:b}).then(r=>r.json()).then(d=>{
        if(d.ok)alert('Wniosek zosta\u0142 wys\u0142any do akceptacji.');
        else alert(d.error||'B\u0142\u0105d');
    }).catch(()=>alert('B\u0142\u0105d po\u0142\u0105czenia'));
}
upd();
</script>

<?php layout_end(); ?>
