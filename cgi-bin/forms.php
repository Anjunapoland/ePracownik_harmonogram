<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();

$formsVisible = get_setting('forms_visible', '1') === '1';
if (!$formsVisible && !is_admin()) {
    header('Location: schedule.php');
    exit;
}

layout_start('Formularze');
?>

<style>
.forms-wrap{max-width:720px;margin:0 auto;padding:16px 24px}
.forms-header{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.forms-hicon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.forms-hicon svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.forms-htitle{font-size:24px;font-weight:800;color:#1c1917;letter-spacing:-.02em}

.form-card{background:#fff;border:1.5px solid #f5f5f4;border-radius:16px;padding:20px 24px;margin-bottom:12px;display:flex;align-items:center;gap:16px;transition:border-color .2s,box-shadow .2s,transform .2s;cursor:pointer;text-decoration:none;color:inherit}
.form-card:hover{border-color:#fed7aa;box-shadow:0 8px 24px rgba(0,0,0,.06);transform:translateY(-2px)}
.form-card-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#fff7ed,#ffedd5);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.form-card-icon svg{width:22px;height:22px;stroke:#ea580c;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.form-card-info{flex:1;min-width:0}
.form-card-title{font-size:14px;font-weight:700;color:#1c1917;margin-bottom:2px}
.form-card-desc{font-size:12px;color:#78716c;line-height:1.4}
.form-card-arrow{color:#d6d3d1;flex-shrink:0;transition:color .2s,transform .2s}
.form-card:hover .form-card-arrow{color:#ea580c;transform:translateX(3px)}

<?php if(!$formsVisible && is_admin()): ?>
.forms-disabled-info{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#991b1b}
<?php endif;?>
</style>

<div class="forms-wrap">
    <div class="forms-header">
        <div class="forms-hicon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div class="forms-htitle">Formularze</div>
    </div>

    <?php if(!$formsVisible && is_admin()): ?>
    <div class="forms-disabled-info">
        Ta sekcja jest obecnie ukryta dla pracownikow. Mozesz ja wlaczyc w <a href="settings.php" style="color:#991b1b;font-weight:700">Ustawieniach</a>.
    </div>
    <?php endif;?>

    <a href="form_wifi.php" class="form-card">
        <div class="form-card-icon">
            <svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
        </div>
        <div class="form-card-info">
            <div class="form-card-title">Oswiadczenie Wi-Fi SCK</div>
            <div class="form-card-desc">Oswiadczenie pracownika dotyczace przekazania klucza dostepu do sieci Wi-Fi Strzegomskiego Centrum Kultury</div>
        </div>
        <svg class="form-card-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    <a href="form_overtime.php" class="form-card">
        <div class="form-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="form-card-info">
            <div class="form-card-title">Wniosek o czas wolny za nadgodziny</div>
            <div class="form-card-desc">Wniosek o udzielenie czasu wolnego w zamian za przepracowane godziny ponadnormatywne</div>
        </div>
        <svg class="form-card-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    <a href="form_leave.php" class="form-card">
        <div class="form-card-icon">
            <svg viewBox="0 0 24 24"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/></svg>
        </div>
        <div class="form-card-info">
            <div class="form-card-title">Wniosek o urlop wypoczynkowy</div>
            <div class="form-card-desc">Wniosek o udzielenie urlopu wypoczynkowego z podaniem terminu i liczby dni</div>
        </div>
        <svg class="form-card-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

</div>

<?php layout_end(); ?>
