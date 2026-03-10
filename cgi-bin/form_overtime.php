<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$formsVisible = get_setting('forms_visible', '1') === '1';
if (!$formsVisible && !is_admin()) { header('Location: schedule.php'); exit; }

// Fetch overtime hours from DB
$currentYear = (int)date('Y');
$stmtOt = get_db()->prepare('SELECT overtime_hours FROM leave_balances WHERE user_id=? AND year=?');
$stmtOt->execute([$user['id'], $currentYear]);
$otRow = $stmtOt->fetch();
$userOvertime = $otRow ? (float)$otRow['overtime_hours'] : 0;

layout_start('Wniosek o czas wolny');
?>

<style>
.fwrap{max-width:800px;margin:0 auto;padding:16px 24px}
.fheader{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px}
.fheader-left{display:flex;align-items:center;gap:12px}
.fhicon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.fhicon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.fhtitle{font-size:18px;font-weight:800;color:#1c1917}
.fback{font-size:12px;color:#78716c;text-decoration:none;display:flex;align-items:center;gap:4px}
.fback:hover{color:#ea580c}
.fback svg{width:14px;height:14px}

/* ── FORM GRID ── */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px;margin-bottom:18px}
.fg-full{grid-column:1/-1}
@media(max-width:560px){.fg{grid-template-columns:1fr}}
.fg-label{font-size:12px;font-weight:700;color:#1c1917;margin-bottom:4px;display:block}
.fg-hint{font-size:11px;color:#a8a29e;margin-bottom:4px;display:block}
.fg-input{width:100%;padding:10px 14px;border:1.5px solid #e7e5e4;border-radius:10px;font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;color:#1c1917;box-sizing:border-box;background:#fff}
.fg-input:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);outline:none}
.fg-input::placeholder{color:#d6d3d1}

/* ── DYNAMIC ROWS ── */
.ot-section-title{font-size:13px;font-weight:700;color:#1c1917;margin-bottom:8px;display:flex;align-items:center;gap:8px}
.ot-section-title svg{width:16px;height:16px;stroke:#ea580c}
.ot-rows{margin-bottom:14px}
.ot-row{display:grid;grid-template-columns:32px 1fr 120px 32px;gap:8px;align-items:center;margin-bottom:6px;animation:otIn .25s ease-out}
@keyframes otIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.ot-num{width:32px;height:38px;border-radius:8px;background:linear-gradient(135deg,#fff7ed,#ffedd5);font-size:11px;font-weight:800;color:#ea580c;display:flex;align-items:center;justify-content:center}
.ot-del{width:32px;height:38px;border:none;background:none;color:#d6d3d1;cursor:pointer;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:color .15s,background .15s}
.ot-del:hover{color:#dc2626;background:#fef2f2}
@media(max-width:560px){.ot-row{grid-template-columns:28px 1fr 90px 28px}}

/* ── SUMMARY ── */
.ot-sum{background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1.5px solid #fed7aa;border-radius:14px;padding:16px 20px;margin-bottom:18px;display:flex;gap:24px;flex-wrap:wrap;align-items:center}
.ot-sum-item{display:flex;flex-direction:column}
.ot-sum-label{font-size:11px;font-weight:600;color:#9a3412;text-transform:uppercase;letter-spacing:.03em}
.ot-sum-val{font-size:22px;font-weight:900;color:#ea580c;letter-spacing:-.02em}
.ot-sum-sep{width:1px;height:36px;background:#fdba74}

/* ── BUTTONS ── */
.ot-btns{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}

/* ── PREVIEW ── */
.fpreview{background:#fff;border:1.5px solid #e7e5e4;border-radius:16px;overflow:hidden}
.fpreview-head{background:#fafaf9;padding:10px 20px;border-bottom:1px solid #f5f5f4}
.fpreview-label{font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em}
.fdoc{padding:28px 32px;font-family:'Times New Roman',Times,serif;font-size:12px;line-height:1.7;color:#000}
.fdoc-field{border-bottom:1px dotted #999;display:inline-block;min-width:140px;padding:0 4px;font-weight:600;color:#1c1917}
.fdoc-field-sm{min-width:50px}
.fdoc-title{text-align:center;font-size:14px;font-weight:700;margin:24px 0 16px}
.fdoc-table{width:100%;border-collapse:collapse;margin:12px 0}
.fdoc-table th,.fdoc-table td{border:1px solid #999;padding:5px 10px;font-size:11px}
.fdoc-table th{background:#f5f5f5;font-weight:700;text-align:left}
.fdoc-table td{text-align:left}
.fdoc-sig{margin-top:48px;display:grid;grid-template-columns:1fr 1fr;gap:60px}
.fdoc-sig-item{text-align:center}
.fdoc-sig-line{border-top:1px solid #999;margin-top:50px;padding-top:4px;font-size:10px;color:#666}

@media print{
    body *{visibility:hidden}
    .fpreview,.fpreview *{visibility:visible}
    .fpreview{position:absolute;left:0;top:0;width:100%;border:none;border-radius:0}
    .fpreview-head{display:none}
    .fdoc{padding:15mm 20mm;font-size:11pt;line-height:1.5}
    .fdoc-title{font-size:13pt}
    .fdoc-table th,.fdoc-table td{font-size:10pt}
}
</style>

<div class="fwrap">
    <div class="fheader">
        <div class="fheader-left">
            <div class="fhicon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="fhtitle">Wniosek o czas wolny za nadgodziny</div>
        </div>
        <a href="forms.php" class="fback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Powr&oacute;t do listy
        </a>
    </div>

    <!-- ── FORM FIELDS ── -->
    <div class="fg">
        <div>
            <label class="fg-label">Imi&#281; i nazwisko</label>
            <input class="fg-input" type="text" id="otName" value="<?=h($user['full_name'])?>" oninput="up()">
        </div>
        <div>
            <label class="fg-label">Stanowisko s&#322;u&#380;bowe</label>
            <input class="fg-input" type="text" id="otPosition" placeholder="np. specjalista ds. kultury" oninput="up()">
        </div>
        <div>
            <label class="fg-label">Data wniosku</label>
            <input class="fg-input" type="date" id="otDate" value="<?=date('Y-m-d')?>" oninput="up()">
        </div>
        <div>
            <label class="fg-label">&#321;&#261;czna liczba godzin nadliczbowych</label>
            <label class="fg-hint">Ca&#322;kowita pula nadgodzin na Twoim koncie</label>
            <input class="fg-input" type="number" id="otTotal" value="<?=$userOvertime?>" min="0" step="0.5" oninput="up()">
        </div>
    </div>

    <!-- ── DYNAMIC ROWS ── -->
    <div class="ot-section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
        Dni wolne do odbioru
    </div>
    <p style="font-size:11px;color:#a8a29e;margin:0 0 10px">Wpisz daty i liczb&#281; godzin do odbioru w ka&#380;dym dniu (maks. 14 wierszy)</p>
    <div class="ot-rows" id="otRows">
        <div class="ot-row">
            <span class="ot-num">1</span>
            <input class="fg-input ot-date" type="date" oninput="up()">
            <input class="fg-input ot-hrs" type="number" value="8.5" min="0" step="0.5" placeholder="godz." oninput="up()">
            <button class="ot-del" onclick="delRow(this)" title="Usu&#324;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>

    <!-- ── SUMMARY ── -->
    <div class="ot-sum">
        <div class="ot-sum-item">
            <span class="ot-sum-label">Razem do odbioru</span>
            <span class="ot-sum-val" id="otSumH">8.5 godz.</span>
        </div>
        <div class="ot-sum-sep"></div>
        <div class="ot-sum-item">
            <span class="ot-sum-label">Pozostanie na koncie</span>
            <span class="ot-sum-val" id="otRemH"><?=max(0,$userOvertime - 8.5)?> godz.</span>
        </div>
    </div>

    <!-- ── BUTTONS ── -->
    <div class="ot-btns">
        <button class="btn btn-primary" onclick="addRow()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Dodaj kolejny dzie&#324;
        </button>
        <button class="btn btn-primary" onclick="doPrint()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Drukuj / Zapisz PDF
        </button>
    </div>

    <!-- ── DOCUMENT PREVIEW ── -->
    <div class="fpreview" id="printArea">
        <div class="fpreview-head">
            <span class="fpreview-label">Podgl&#261;d dokumentu</span>
        </div>
        <div class="fdoc">
            <div style="text-align:right;font-size:11px;margin-bottom:8px">
                Strzegom, dnia <span class="fdoc-field" id="docDate" style="min-width:100px"></span> r.
            </div>

            <p style="margin:4px 0">Imi&#281; i nazwisko: <span class="fdoc-field" id="docName"><?=h($user['full_name'])?></span></p>
            <p style="margin:4px 0">Stanowisko s&#322;u&#380;bowe: <span class="fdoc-field" id="docPos"></span></p>

            <div class="fdoc-title">WNIOSEK<br>o udzielenie czasu wolnego za godziny ponadnormatywne</div>

            <p style="text-indent:2em;text-align:justify">
                W zwi&#261;zku z przepracowanymi godzinami ponadnormatywnymi
                <span class="fdoc-field fdoc-field-sm" id="docTotal"><?=$userOvertime?></span> godz.,
                prosz&#281; o udzielenie mi czasu wolnego w dniach:
            </p>

            <table class="fdoc-table">
                <thead><tr><th style="width:35px">Lp.</th><th>Data</th><th style="width:110px">Liczba godzin</th></tr></thead>
                <tbody id="docTb"><tr><td>1</td><td></td><td>8,5</td></tr></tbody>
            </table>

            <p style="margin-top:14px">
                Razem do odbioru: <span class="fdoc-field fdoc-field-sm" id="docSum">8,5</span> godz.
                (do wykorzystania pozosta&#322;o: <span class="fdoc-field fdoc-field-sm" id="docRem">0</span> godz.)
            </p>

            <div class="fdoc-sig">
                <div class="fdoc-sig-item"><div class="fdoc-sig-line">Podpis pracownika</div></div>
                <div class="fdoc-sig-item"><div class="fdoc-sig-line">Podpis prze&#322;o&#380;onego</div></div>
            </div>

            <div style="margin-top:40px;border-top:1px solid #ccc;padding-top:12px">
                <p style="font-size:11px;color:#666;margin:0"><strong>Decyzja Dyrektora:</strong></p>
                <p style="margin:6px 0">Wyra&#380;a si&#281; zgod&#281; / Nie wyra&#380;a si&#281; zgody*</p>
                <div style="margin-top:30px;width:250px">
                    <div class="fdoc-sig-line">Data i podpis Dyrektora</div>
                </div>
                <p style="font-size:9px;color:#999;margin-top:12px">* niepotrzebne skre&#347;li&#263;</p>
            </div>
        </div>
    </div>
</div>

<script>
var MAX_ROWS=14;
function rows(){return document.querySelectorAll('.ot-row');}
function fmtD(v){if(!v)return '';var p=v.split('-');return p[2]+'.'+p[1]+'.'+p[0];}
function fmtN(n){return String(n).replace('.',',');}

function addRow(){
    var r=rows();
    if(r.length>=MAX_ROWS){alert('Maksymalnie '+MAX_ROWS+' wierszy.');return;}
    var d=document.createElement('div');d.className='ot-row';
    d.innerHTML='<span class="ot-num">'+(r.length+1)+'</span>'
        +'<input class="fg-input ot-date" type="date" oninput="up()">'
        +'<input class="fg-input ot-hrs" type="number" value="8.5" min="0" step="0.5" placeholder="godz." oninput="up()">'
        +'<button class="ot-del" onclick="delRow(this)" title="Usu\u0144"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
    document.getElementById('otRows').appendChild(d);
    renum();up();
}
function delRow(btn){
    if(rows().length<=1){alert('Musi pozosta\u0107 przynajmniej jeden wiersz.');return;}
    btn.closest('.ot-row').remove();renum();up();
}
function renum(){rows().forEach(function(r,i){r.querySelector('.ot-num').textContent=i+1;});}

function up(){
    var name=document.getElementById('otName').value;
    var pos=document.getElementById('otPosition').value;
    var dt=document.getElementById('otDate').value;
    var total=parseFloat(document.getElementById('otTotal').value)||0;

    document.getElementById('docName').textContent=name;
    document.getElementById('docPos').textContent=pos;
    document.getElementById('docDate').textContent=fmtD(dt);
    document.getElementById('docTotal').textContent=fmtN(total);

    var tb=document.getElementById('docTb');tb.innerHTML='';
    var sum=0;
    rows().forEach(function(r,i){
        var d=r.querySelector('.ot-date').value;
        var h=parseFloat(r.querySelector('.ot-hrs').value)||0;
        sum+=h;
        var tr=document.createElement('tr');
        tr.innerHTML='<td>'+(i+1)+'</td><td>'+fmtD(d)+'</td><td>'+fmtN(h)+'</td>';
        tb.appendChild(tr);
    });
    var rem=Math.max(0,total-sum);
    document.getElementById('otSumH').textContent=fmtN(sum)+' godz.';
    document.getElementById('otRemH').textContent=fmtN(rem)+' godz.';
    document.getElementById('docSum').textContent=fmtN(sum);
    document.getElementById('docRem').textContent=fmtN(rem);
}
function doPrint(){
    if(!document.getElementById('otName').value.trim()){alert('Wype\u0142nij imi\u0119 i nazwisko.');document.getElementById('otName').focus();return;}
    window.print();
}
up();
</script>

<?php layout_end(); ?>
