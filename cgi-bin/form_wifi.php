<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

$formsVisible = get_setting('forms_visible', '1') === '1';
if (!$formsVisible && !is_admin()) {
    header('Location: schedule.php');
    exit;
}

layout_start('Oswiadczenie Wi-Fi');
?>

<style>
/* FORM PAGE */
.fwrap{max-width:800px;margin:0 auto;padding:16px 24px}
.fheader{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.fheader-left{display:flex;align-items:center;gap:12px}
.fhicon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.fhicon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.fhtitle{font-size:18px;font-weight:800;color:#1c1917}
.fback{font-size:12px;color:#78716c;text-decoration:none;display:flex;align-items:center;gap:4px}
.fback:hover{color:#ea580c}
.fback svg{width:14px;height:14px}

/* Input fields */
.finput-group{margin-bottom:20px}
.finput-label{font-size:12px;font-weight:700;color:#1c1917;margin-bottom:6px;display:block}
.finput-hint{font-size:11px;color:#a8a29e;margin-bottom:6px;display:block}
.finput{width:100%;padding:10px 14px;border:1.5px solid #e7e5e4;border-radius:10px;font-size:14px;font-family:inherit;transition:border-color .2s,box-shadow .2s;color:#1c1917}
.finput:focus{border-color:#ea580c;box-shadow:0 0 0 3px rgba(234,88,12,.1);outline:none}
.factions{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}

/* Preview card */
.fpreview{background:#fff;border:1.5px solid #e7e5e4;border-radius:16px;padding:0;overflow:hidden}
.fpreview-head{background:#fafaf9;padding:10px 20px;border-bottom:1px solid #f5f5f4;display:flex;align-items:center;justify-content:space-between}
.fpreview-label{font-size:11px;font-weight:700;color:#78716c;text-transform:uppercase;letter-spacing:.04em}

/* Document content */
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

/* Print styles */
@media print {
    body *{visibility:hidden}
    .fpreview,.fpreview *{visibility:visible}
    .fpreview{position:absolute;left:0;top:0;width:100%;border:none;border-radius:0}
    .fpreview-head{display:none}
    .fdoc{padding:20mm;font-size:11pt;line-height:1.5}
    .fdoc-title{font-size:14pt}
    .fdoc-subtitle{font-size:11pt}
    .fdoc-field{border-bottom:1px dotted #333}
    .fdoc-section{font-size:11pt}
    .fdoc p{font-size:10.5pt}
    .fdoc-list li{font-size:10pt}
    .fdoc-stamp{border:1px dashed #999}
}
</style>

<div class="fwrap">
    <div class="fheader">
        <div class="fheader-left">
            <div class="fhicon">
                <svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
            </div>
            <div class="fhtitle">Oswiadczenie Wi-Fi SCK</div>
        </div>
        <a href="forms.php" class="fback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Powrot do listy
        </a>
    </div>

    <!-- Editable fields -->
    <div class="finput-group">
        <label class="finput-label">Imie i nazwisko pracownika</label>
        <label class="finput-hint">To pole zostanie wstawione do oswiadczenia</label>
        <input class="finput" type="text" id="fName" value="<?=h($user['full_name'])?>" placeholder="Wpisz imie i nazwisko">
    </div>
    <div class="finput-group">
        <label class="finput-label">Miejscowosc i data</label>
        <label class="finput-hint">Np. Strzegom, <?=date('d.m.Y')?></label>
        <input class="finput" type="text" id="fPlace" placeholder="Np. Strzegom, <?=date('d.m.Y')?>">
    </div>

    <div class="factions">
        <button class="btn btn-primary" onclick="doPrint()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:-2px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
            Drukuj / Zapisz PDF
        </button>
    </div>

    <!-- Document preview -->
    <div class="fpreview" id="printArea">
        <div class="fpreview-head">
            <span class="fpreview-label">Podglad dokumentu</span>
        </div>
        <div class="fdoc">
            <div class="fdoc-title">OSWIADCZENIE PRACOWNIKA</div>
            <div class="fdoc-subtitle">dotyczace przekazania klucza dostepu do sieci Wi-Fi<br>Strzegomskiego Centrum Kultury</div>

            <p>Ja, nizej podpisany/a, <span class="fdoc-field" id="docName"><?=h($user['full_name'])?></span>, zatrudniony/a w Strzegomskim Centrum Kultury, oswiadczam, ze w dniu <span class="fdoc-field-block"></span> otrzymalem/am od Administratora sieci / osoby uprawnionej dostep do firmowej sieci bezprzewodowej Wi-Fi, na zasadach okreslonych ponizej.</p>

            <div class="fdoc-section">1. Dane sieci przekazane do mojej wiadomosci</div>
            <p>Ponizsze dane zostaly przekazane wylacznie do mojej wiadomosci i przeznaczone sa do korzystania w zwiazku z wykonywaniem obowiazkow sluzbowych na terenie Strzegomskiego Centrum Kultury.</p>
            <p>Nazwa sieci (SSID): <span class="fdoc-field-block"></span></p>
            <p>Klucz / haslo dostepu: <span class="fdoc-field-block"></span></p>
            <p>Lokalizacja / obszar obowiazywania (opcjonalnie): <span class="fdoc-field-block"></span></p>
            <p>Urzadzenie/urzadzenia dopuszczone do uzycia (opcjonalnie): <span class="fdoc-field-block"></span></p>

            <div class="fdoc-section">2. Zasady poufnosci i zakaz udostepniania</div>
            <p>Przyjmuje do wiadomosci, ze klucz/haslo do sieci Wi-Fi ma charakter poufny i zostal mi przekazany wylacznie do uzytku wlasnego w celach sluzbowych.</p>
            <p>Zobowiazuje sie do zachowania w tajemnicy otrzymanego klucza/hasla oraz do jego ochrony przed nieuprawnionym ujawnieniem lub wykorzystaniem.</p>
            <p>Oswiadczam, ze obowiazuje mnie bezwzgledny zakaz przekazywania klucza/hasla osobom postronnym oraz innym pracownikom Strzegomskiego Centrum Kultury, chyba ze wyraznie stanowi inaczej pisemne polecenie sluzbowe.</p>
            <p>Wyjatek od zakazu, o ktorym mowa powyzej, stanowi wylacznie polecenie sluzbowe wydane przez Dyrektora Strzegomskiego Centrum Kultury lub osobe przez niego upowazniona, utrwalone w formie pisemnej lub elektronicznej.</p>

            <div class="fdoc-section">3. Okres waznosci i zasada zmiany hasla</div>
            <p>Dostep ma charakter upowaznienia sluzbowego i moze zostac cofniety w kazdym czasie, w szczegolnosci w przypadku zmiany stanowiska pracy, rozwiazania stosunku pracy lub naruszenia zasad niniejszego oswiadczenia.</p>

            <div class="fdoc-section">4. Zasada korzystania wylacznie z zatwierdzonych urzadzen</div>
            <p>Dostep moze byc wykorzystywany wylacznie na urzadzeniach zaakceptowanych do pracy w sieci SCK.</p>

            <div class="fdoc-section">5. Minimalne wymogi bezpieczenstwa po stronie pracownika</div>
            <p>Potwierdzam, ze urzadzenie wykorzystywane do dostepu do sieci spelnia co najmniej nastepujace wymagania bezpieczenstwa:</p>
            <ul class="fdoc-list">
                <li>aktywna blokada ekranu (PIN/haslo/biometria) oraz automatyczne blokowanie po bezczynnosci,</li>
                <li>aktualny system operacyjny i oprogramowanie (w tym aktualizacje zabezpieczen),</li>
                <li>nieudostepnianie urzadzenia osobom trzecim oraz niepozostawianie go bez nadzoru w sposob umozliwiajacy dostep nieuprawnionym osobom.</li>
            </ul>

            <div class="fdoc-section">6. Odpowiedzialnosc i bezpieczenstwo</div>
            <p>Przyjmuje do wiadomosci, ze nieuprawnione ujawnienie klucza/hasla lub dopuszczenie do korzystania z sieci przez osoby nieuprawnione moze skutkowac odpowiedzialnoscia na zasadach ogolnych, w tym odpowiedzialnoscia dyscyplinarna lub porzadkowa.</p>
            <p>Przyjmuje do wiadomosci, ze naruszenie zasad moze skutkowac odpowiedzialnoscia porzadkowa lub innymi konsekwencjami przewidzianymi przez prawo pracy.</p>

            <div class="fdoc-section">7. Kanal zglaszania incydentow</div>
            <p>Wszelkie incydenty bezpieczenstwa (w szczegolnosci: podejrzenie ujawnienia klucza/hasla, utrata urzadzenia, podejrzane zachowania w sieci) nalezy niezwlocznie zglosic do Administratora sieci lub bezposredniego przelozonego.</p>

            <div class="fdoc-section">8. Wnioski o przyznanie klucza dostepu</div>
            <p>Wnioski o przyznanie dostepu do sieci Wi-Fi nalezy skladac na adres e-mail: t.smaglowski@sck.strzegom.pl</p>

            <p style="margin-top:24px">Miejscowosc i data: <span class="fdoc-field" id="docPlace"></span></p>

            <div class="fdoc-sig">
                <div>
                    <div class="fdoc-sig-block">Czytelny podpis pracownika</div>
                </div>
                <div></div>
            </div>

            <div style="margin-top:32px;padding-top:16px;border-top:1px solid #eee">
                <p style="font-weight:700">Potwierdzenie przekazania (Administrator sieci / osoba upowazniona):</p>
                <p>Imie i nazwisko: <span class="fdoc-field-block"></span></p>
                <p>Podpis: <span class="fdoc-field-block"></span></p>
            </div>

            <div style="margin-top:24px">
                <p style="font-weight:700;font-size:11px">Pieczatka Strzegomskiego Centrum Kultury:</p>
                <div class="fdoc-stamp">Miejsce na pieczatke</div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('fName').addEventListener('input',function(){
    document.getElementById('docName').textContent=this.value||'';
});
document.getElementById('fPlace').addEventListener('input',function(){
    document.getElementById('docPlace').textContent=this.value||'';
});
function doPrint(){
    var n=document.getElementById('fName').value.trim();
    if(!n){alert('Wypelnij imie i nazwisko.');document.getElementById('fName').focus();return;}
    window.print();
}
</script>

<?php layout_end(); ?>
