<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
ensure_users_profile_columns();
$formsVisible = get_setting('forms_visible', '1') === '1';
if (!$formsVisible && !is_admin()) { header('Location: schedule.php'); exit; }
layout_start('Oświadczenie Wi-Fi');
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
.factions{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.fpreview{background:#fff;border:1.5px solid #e7e5e4;border-radius:16px;padding:0;overflow:hidden}
.fpreview-head{background:#fafaf9;padding:10px 20px;border-bottom:1px solid #f5f5f4}
.fpreview-label{font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em}
.fdoc{padding:32px;font-family:'Times New Roman',Times,serif;font-size:12px;line-height:1.6;color:#000}
.fdoc-title{text-align:center;font-size:16px;font-weight:700;margin-bottom:4px;text-transform:uppercase}
.fdoc-subtitle{text-align:center;font-size:12px;margin-bottom:24px}
.fdoc-section{font-weight:700;margin-top:16px;margin-bottom:4px}
.fdoc p{margin:6px 0;text-align:justify}
.fdoc-field{border-bottom:1px dotted #999;display:inline-block;min-width:200px;padding:0 4px;font-weight:600;color:#1c1917}
.fdoc-field-block{border:1px solid #ccc;min-height:28px;padding:4px 8px;margin:4px 0;border-radius:2px}
.fdoc-sig{margin-top:24px;display:grid;grid-template-columns:1fr 1fr;gap:40px}
.fdoc-sig-block{border-top:1px solid #999;padding-top:4px;text-align:center;font-size:11px;color:#666;margin-top:50px}
.fdoc-list{margin:4px 0 4px 16px;padding:0}
.fdoc-list li{margin:3px 0;font-size:11.5px}
.fdoc-stamp{border:1px dashed #ccc;width:200px;height:70px;margin:12px 0;display:flex;align-items:center;justify-content:center;font-size:10px;color:#aaa}
@media print{
    body *{visibility:hidden}
    .fpreview,.fpreview *{visibility:visible}
    .fpreview{position:absolute;left:0;top:0;width:100%;border:none;border-radius:0}
    .fpreview-head{display:none}
    .fdoc{padding:20mm;font-size:11pt;line-height:1.5}
    .fdoc-title{font-size:14pt}
    .fdoc-subtitle{font-size:11pt}
}
</style>

<div class="fwrap">
    <div class="fheader">
        <div class="fheader-left">
            <div class="fhicon">
                <svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
            </div>
            <div class="fhtitle">Oświadczenie Wi-Fi SCK</div>
        </div>
        <a href="forms.php" class="fback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Powrót do listy
        </a>
    </div>

    <div class="fg">
        <div>
            <label class="fg-label">Imię i nazwisko pracownika</label>
            <input class="fg-input" type="text" id="fName" value="<?=h($user['full_name'])?>" oninput="up()">
        </div>
        <div>
            <label class="fg-label">Stanowisko służbowe</label>
            <input class="fg-input" type="text" id="fPosition" value="<?=h($user['job_title'] ?? '')?>" oninput="up()">
        </div>
        <div>
            <label class="fg-label">Miejscowość i data</label>
            <input class="fg-input" type="text" id="fPlace" value="Strzegom, <?=date('d.m.Y')?>" oninput="up()">
        </div>
    </div>

    <div class="factions">
        <button class="btn btn-primary" onclick="doPrint()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Drukuj / Zapisz PDF
        </button>
    </div>

    <div class="fpreview" id="printArea">
        <div class="fpreview-head"><span class="fpreview-label">Podgląd dokumentu</span></div>
        <div class="fdoc">
            <div class="fdoc-title">OŚWIADCZENIE PRACOWNIKA</div>
            <div class="fdoc-subtitle">dotyczące przekazania klucza dostępu do sieci Wi-Fi<br>Strzegomskiego Centrum Kultury</div>

            <p>Ja, niżej podpisany/a, <span class="fdoc-field" id="docName"><?=h($user['full_name'])?></span>, zatrudniony/a na stanowisku <span class="fdoc-field" id="docPosition"><?=h($user['job_title'] ?? '')?></span> w Strzegomskim Centrum Kultury, oświadczam, że w dniu <span class="fdoc-field-block"></span> otrzymałem/am od Administratora sieci / osoby uprawnionej dostęp do firmowej sieci bezprzewodowej Wi-Fi, na zasadach określonych poniżej.</p>

            <div class="fdoc-section">1. Dane sieci przekazane do mojej wiadomości</div>
            <p>Poniższe dane zostały przekazane wyłącznie do mojej wiadomości i przeznaczone są do korzystania w związku z wykonywaniem obowiązków służbowych na terenie Strzegomskiego Centrum Kultury.</p>
            <p>Nazwa sieci (SSID): <span class="fdoc-field-block"></span></p>
            <p>Klucz / hasło dostępu: <span class="fdoc-field-block"></span></p>
            <p>Lokalizacja / obszar obowiązywania (opcjonalnie): <span class="fdoc-field-block"></span></p>
            <p>Urządzenie/urządzenia dopuszczone do użycia (opcjonalnie): <span class="fdoc-field-block"></span></p>

            <div class="fdoc-section">2. Zasady poufności i zakaz udostępniania</div>
            <p>Przyjmuję do wiadomości, że klucz/hasło do sieci Wi-Fi ma charakter poufny i został mi przekazany wyłącznie do użytku własnego w celach służbowych.</p>
            <p>Zobowiązuję się do zachowania w tajemnicy otrzymanego klucza/hasła oraz do jego ochrony przed nieuprawnionym ujawnieniem lub wykorzystaniem.</p>
            <p>Oświadczam, że obowiązuje mnie bezwzględny zakaz przekazywania klucza/hasła osobom postronnym oraz innym pracownikom Strzegomskiego Centrum Kultury, chyba że wyraźnie stanowi inaczej pisemne polecenie służbowe.</p>
            <p>Wyjątek od zakazu, o którym mowa powyżej, stanowi wyłącznie polecenie służbowe wydane przez Dyrektora Strzegomskiego Centrum Kultury lub osobę przez niego upoważnioną, utrwalone w formie pisemnej lub elektronicznej.</p>

            <div class="fdoc-section">3. Okres ważności i zasada zmiany hasła</div>
            <p>Dostęp ma charakter upoważnienia służbowego i może zostać cofnięty w każdym czasie, w szczególności w przypadku zmiany stanowiska pracy, rozwiązania stosunku pracy lub naruszenia zasad niniejszego oświadczenia.</p>

            <div class="fdoc-section">4. Zasada korzystania wyłącznie z zatwierdzonych urządzeń</div>
            <p>Dostęp może być wykorzystywany wyłącznie na urządzeniach zaakceptowanych do pracy w sieci SCK.</p>

            <div class="fdoc-section">5. Minimalne wymogi bezpieczeństwa po stronie pracownika</div>
            <p>Potwierdzam, że urządzenie wykorzystywane do dostępu do sieci spełnia co najmniej następujące wymagania bezpieczeństwa:</p>
            <ul class="fdoc-list">
                <li>aktywna blokada ekranu (PIN/hasło/biometria) oraz automatyczne blokowanie po bezczynności,</li>
                <li>aktualny system operacyjny i oprogramowanie (w tym aktualizacje zabezpieczeń),</li>
                <li>nieudostępnianie urządzenia osobom trzecim oraz niepozostawianie go bez nadzoru w sposób umożliwiający dostęp nieuprawnionym osobom.</li>
            </ul>

            <div class="fdoc-section">6. Odpowiedzialność i bezpieczeństwo</div>
            <p>Przyjmuję do wiadomości, że nieuprawnione ujawnienie klucza/hasła lub dopuszczenie do korzystania z sieci przez osoby nieuprawnione może skutkować odpowiedzialnością na zasadach ogólnych, w tym odpowiedzialnością dyscyplinarną lub porządkową.</p>
            <p>Przyjmuję do wiadomości, że naruszenie zasad może skutkować odpowiedzialnością porządkową lub innymi konsekwencjami przewidzianymi przez prawo pracy.</p>

            <div class="fdoc-section">7. Kanał zgłaszania incydentów</div>
            <p>Wszelkie incydenty bezpieczeństwa (w szczególności: podejrzenie ujawnienia klucza/hasła, utrata urządzenia, podejrzane zachowania w sieci) należy niezwłocznie zgłosić do Administratora sieci lub bezpośredniego przełożonego.</p>

            <div class="fdoc-section">8. Wnioski o przyznanie klucza dostępu</div>
            <p>Wnioski o przyznanie dostępu do sieci Wi-Fi należy składać na adres e-mail: t.smaglowski@sck.strzegom.pl</p>

            <p style="margin-top:24px">Miejscowość i data: <span class="fdoc-field" id="docPlace">Strzegom, <?=date('d.m.Y')?></span></p>

            <div class="fdoc-sig">
                <div><div class="fdoc-sig-block">Czytelny podpis pracownika</div></div>
                <div></div>
            </div>

            <div style="margin-top:32px;padding-top:16px;border-top:1px solid #eee">
                <p style="font-weight:700">Potwierdzenie przekazania (Administrator sieci / osoba upoważniona):</p>
                <p>Imię i nazwisko: <span class="fdoc-field-block"></span></p>
                <p>Podpis: <span class="fdoc-field-block"></span></p>
            </div>

            <div style="margin-top:24px">
                <p style="font-weight:700;font-size:11px">Pieczątka Strzegomskiego Centrum Kultury:</p>
                <div class="fdoc-stamp">Miejsce na pieczątkę</div>
            </div>
        </div>
    </div>
</div>

<script>
function up(){
    document.getElementById('docName').textContent=document.getElementById('fName').value;
    document.getElementById('docPlace').textContent=document.getElementById('fPlace').value;
    document.getElementById('docPosition').textContent=document.getElementById('fPosition').value;
}
function doPrint(){
    if(!document.getElementById('fName').value.trim()){alert('Wypełnij imię i nazwisko.');document.getElementById('fName').focus();return;}
    window.print();
}
</script>

<?php layout_end(); ?>
