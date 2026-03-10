<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { header('Location: schedule.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt  = get_db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if (!$u)                                    $error = 'Nie znaleziono konta z tym adresem e-mail';
    elseif (!$u['active'])                      $error = 'Konto jest zablokowane. Skontaktuj się z administratorem.';
    elseif (!password_verify($pass, $u['password'])) $error = 'Nieprawidłowe hasło';
    else {
        $_SESSION['user_id'] = $u['id'];
        session_regenerate_id(true);
        header('Location: ' . ($u['must_change_password'] ? 'profile.php?force=1' : 'schedule.php'));
        exit;
    }
}
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Logowanie — SCK Harmonogram</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-icon">📅</div>
    <h1>SCK Harmonogram</h1>
    <p class="sub">Strzegomskie Centrum Kultury</p>
    <?php if ($error): ?><div class="alert alert-danger">🔒 <?= h($error) ?></div><?php endif; ?>
    <form method="post">
        <label class="lbl">E-mail służbowy</label>
        <input type="email" name="email" class="input" value="<?= h($_POST['email']??'') ?>" required autofocus placeholder="email@sck.strzegom.pl">
        <label class="lbl">Hasło</label>
        <input type="password" name="password" class="input" required placeholder="••••••••">
        <button type="submit" class="btn btn-primary btn-block">🔒 Zaloguj się</button>
    </form>
</div>
</body></html>
