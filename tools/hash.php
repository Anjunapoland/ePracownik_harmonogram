<?php
/**
 * NARZĘDZIE DO GENEROWANIA HASHY HASEŁ
 * ⚠️ USUŃ TEN PLIK PO UŻYCIU!
 */
$hash = '';
$pass = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['pass'] ?? '';
    if ($pass) $hash = password_hash($pass, PASSWORD_DEFAULT);
}
?><!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"><title>Hash Generator</title>
<style>body{font-family:system-ui;max-width:500px;margin:40px auto;padding:20px;background:#fafaf9}
h1{color:#ea580c;font-size:20px}input,button{padding:10px;font-size:14px;border-radius:8px;border:1px solid #e7e5e4;margin:4px 0}
input{width:100%}button{background:#ea580c;color:#fff;border:none;cursor:pointer;width:100%}
.result{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:8px;margin-top:12px;word-break:break-all;font-family:monospace;font-size:12px}
.warn{background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;margin:16px 0;color:#dc2626;font-size:13px}
</style></head><body>
<h1>🔑 Generator hashy haseł</h1>
<div class="warn">⚠️ <strong>USUŃ TEN PLIK</strong> po wygenerowaniu hashy! (tools/hash.php)</div>
<form method="post">
    <label><strong>Hasło:</strong></label>
    <input name="pass" value="<?= htmlspecialchars($pass) ?>" placeholder="Wpisz hasło..." autofocus>
    <button type="submit">Generuj hash</button>
</form>
<?php if ($hash): ?>
<div class="result">
    <strong>Hasło:</strong> <?= htmlspecialchars($pass) ?><br><br>
    <strong>Hash:</strong><br><?= htmlspecialchars($hash) ?>
</div>
<p style="font-size:12px;color:#78716c;margin-top:8px">Skopiuj hash i użyj w zapytaniu SQL:<br>
<code>UPDATE users SET password='<?= htmlspecialchars($hash) ?>' WHERE email='...';</code></p>
<?php endif; ?>
</body></html>
