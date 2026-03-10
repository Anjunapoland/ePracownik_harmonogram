<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
if (!is_admin()) { header('Location: schedule.php'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['_token']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['_token'])) {
        $formsVisible = isset($_POST['forms_visible']) ? '1' : '0';
        set_setting('forms_visible', $formsVisible);

        $empView = $_POST['employee_view_mode'] ?? 'own';
        if (in_array($empView, ['own', 'all', 'individual'])) {
            set_setting('employee_view_mode', $empView);
        }

        $saved = true;
    }
}

$formsVisible = get_setting('forms_visible', '1') === '1';
$empViewMode = get_setting('employee_view_mode', 'own');

layout_start('Ustawienia');
?>

<style>
.set-wrap{max-width:640px;margin:0 auto;padding:16px 24px}
.set-header{display:flex;align-items:center;gap:12px;margin-bottom:28px}
.set-hicon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#ea580c,#f97316);display:flex;align-items:center;justify-content:center}
.set-hicon svg{width:20px;height:20px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.set-htitle{font-size:24px;font-weight:800;color:#1c1917;letter-spacing:-.02em}

.set-section{background:#fff;border:1.5px solid #f5f5f4;border-radius:16px;padding:20px 24px;margin-bottom:16px}
.set-section-title{font-size:14px;font-weight:800;color:#1c1917;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.set-section-desc{font-size:12px;color:#78716c;margin-bottom:16px;line-height:1.5}
.set-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid #f5f5f4;gap:12px}
.set-row:first-child{border-top:none}
.set-row-label{font-size:13px;font-weight:600;color:#1c1917}
.set-row-hint{font-size:11px;color:#a8a29e;margin-top:2px}

/* Toggle switch */
.set-toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.set-toggle input{opacity:0;width:0;height:0}
.set-toggle-slider{position:absolute;inset:0;background:#d6d3d1;border-radius:99px;cursor:pointer;transition:background .25s}
.set-toggle-slider::before{content:'';position:absolute;left:3px;top:3px;width:18px;height:18px;background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.set-toggle input:checked+.set-toggle-slider{background:linear-gradient(135deg,#ea580c,#f97316)}
.set-toggle input:checked+.set-toggle-slider::before{transform:translateX(20px)}

/* Select */
.set-select{padding:8px 12px;border:1.5px solid #e7e5e4;border-radius:8px;font-size:13px;font-family:inherit;color:#1c1917;background:#fff;cursor:pointer}
.set-select:focus{border-color:#ea580c;outline:none}

.set-save{margin-top:8px}
.set-saved{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 16px;font-size:12px;color:#166534;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px}
</style>

<div class="set-wrap">
    <div class="set-header">
        <div class="set-hicon">
            <svg viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <div class="set-htitle">Ustawienia</div>
    </div>

    <?php if(!empty($saved)):?>
    <div class="set-saved">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
        Ustawienia zostaly zapisane
    </div>
    <?php endif;?>

    <form method="post">
        <input type="hidden" name="_token" value="<?=h($_SESSION['csrf']??'')?>">

        <div class="set-section">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Formularze
            </div>
            <div class="set-section-desc">Zarzadzaj widocznoscia sekcji formularzy w menu bocznym dla pracownikow.</div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Pokaz "Formularze" w menu</div>
                    <div class="set-row-hint">Gdy wylaczone, pracownicy nie widza opcji Formularze w sidebarze. Administratorzy widza zawsze.</div>
                </div>
                <label class="set-toggle">
                    <input type="checkbox" name="forms_visible" value="1" <?=$formsVisible?'checked':''?>>
                    <span class="set-toggle-slider"></span>
                </label>
            </div>
        </div>

        <div class="set-section">
            <div class="set-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Widocznosc harmonogramu
            </div>
            <div class="set-section-desc">Okresl, czy pracownicy moga widziec harmonogram innych pracownikow.</div>
            <div class="set-row">
                <div>
                    <div class="set-row-label">Tryb widocznosci</div>
                    <div class="set-row-hint">Ustawienie globalne dla wszystkich pracownikow.</div>
                </div>
                <select name="employee_view_mode" class="set-select">
                    <option value="own" <?=$empViewMode==='own'?'selected':''?>>Tylko wlasny grafik</option>
                    <option value="all" <?=$empViewMode==='all'?'selected':''?>>Wszyscy widza calosc</option>
                    <option value="individual" <?=$empViewMode==='individual'?'selected':''?>>Indywidualnie</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary set-save">Zapisz ustawienia</button>
    </form>
</div>

<?php layout_end(); ?>
