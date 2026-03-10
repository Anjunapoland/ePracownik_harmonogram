<?php
/**
 * INSTALATOR / MIGRACJA SCK Harmonogram
 * Bezpiecznie tworzy brakujące tabele i kolumny.
 * NIE NADPISUJE i NIE USUWA istniejących danych.
 * 
 * Po zakończeniu USUŃ ten plik z serwera.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$msg = [];
$ok  = true;

try {
    $db = get_db();
    $msg[] = '✅ Połączenie z bazą danych OK';

    // ── Helper: check if table exists ──
    function tableExists(PDO $db, string $table): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
    // ── Helper: check if column exists ──
    function columnExists(PDO $db, string $table, string $column): bool {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ═══ TABELA: users ═══
    if (!tableExists($db, 'users')) {
        $db->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(191) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(191) NOT NULL,
            department VARCHAR(100) NOT NULL DEFAULT 'SCK',
            employment_fraction DECIMAL(3,2) NOT NULL DEFAULT 1.00,
            role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
            active TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 1,
            can_view_all TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $msg[] = '✅ Tabela users — UTWORZONA';
    } else {
        $msg[] = 'ℹ️ Tabela users — istnieje, dane nienaruszone';
        // Migracja: dodaj can_view_all jeśli brakuje
        if (!columnExists($db, 'users', 'can_view_all')) {
            $db->exec("ALTER TABLE users ADD COLUMN can_view_all TINYINT(1) NOT NULL DEFAULT 0 AFTER must_change_password");
            $msg[] = '  ↳ Dodano kolumnę can_view_all';
        }
    }

    // ═══ TABELA: schedule_entries ═══
    if (!tableExists($db, 'schedule_entries')) {
        $db->exec("CREATE TABLE schedule_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            entry_date DATE NOT NULL,
            shift_type VARCHAR(50) NOT NULL DEFAULT 'standard',
            shift_start TIME DEFAULT NULL,
            shift_end TIME DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_date (user_id, entry_date),
            KEY idx_date (entry_date),
            KEY idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $msg[] = '✅ Tabela schedule_entries — UTWORZONA';
    } else {
        $msg[] = 'ℹ️ Tabela schedule_entries — istnieje, dane nienaruszone';
    }

    // ═══ TABELA: notifications ═══
    if (!tableExists($db, 'notifications')) {
        $db->exec("CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            body TEXT DEFAULT NULL,
            related_date DATE DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_read (user_id, is_read),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $msg[] = '✅ Tabela notifications — UTWORZONA';
    } else {
        $msg[] = 'ℹ️ Tabela notifications — istnieje, dane nienaruszone';
    }

    // ═══ TABELA: settings ═══
    if (!tableExists($db, 'settings')) {
        $db->exec("CREATE TABLE settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('employee_view_mode', 'own')");
        $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('forms_visible', '1')");
        $msg[] = '✅ Tabela settings — UTWORZONA';
    } else {
        $msg[] = 'ℹ️ Tabela settings — istnieje, dane nienaruszone';
    }

    // ═══ TABELA: shift_types ═══
    if (!tableExists($db, 'shift_types')) {
        $db->exec("CREATE TABLE shift_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            label VARCHAR(100) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#fff',
            text_color VARCHAR(20) NOT NULL DEFAULT '#333',
            default_start TIME DEFAULT NULL,
            default_end TIME DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        seed_shift_types();
        $msg[] = '✅ Tabela shift_types — UTWORZONA + domyślne typy zmian dodane';
    } else {
        $msg[] = 'ℹ️ Tabela shift_types — istnieje, dane nienaruszone';
    }

    // ═══ TABELA: leave_balances ═══
    if (!tableExists($db, 'leave_balances')) {
        $db->exec("CREATE TABLE leave_balances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            year INT NOT NULL,
            leave_prev_year DECIMAL(5,1) NOT NULL DEFAULT 0 COMMENT 'Urlop zaległy z poprzedniego roku (dni)',
            leave_current_year DECIMAL(5,1) NOT NULL DEFAULT 0 COMMENT 'Urlop przysługujący w bieżącym roku (dni)',
            overtime_hours DECIMAL(6,1) NOT NULL DEFAULT 0 COMMENT 'Godziny nadliczbowe',
            note TEXT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_year (user_id, year),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $msg[] = '✅ Tabela leave_balances — UTWORZONA';
    } else {
        $msg[] = 'ℹ️ Tabela leave_balances — istnieje, dane nienaruszone';
    }

    // ═══ KONTO ADMINA (tylko jeśli brak użytkowników) ═══
    $userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $db->prepare('INSERT INTO users (email, password, full_name, department, employment_fraction, role, active, must_change_password) VALUES (?,?,?,?,?,?,?,?)')
           ->execute([
               'admin@sck.strzegom.pl',
               password_hash('admin123', PASSWORD_DEFAULT),
               'Administrator HR',
               'Kadry',
               1.00,
               'admin',
               1,
               0
           ]);
        $msg[] = '✅ Konto admina utworzone: admin@sck.strzegom.pl / admin123';
        $msg[] = '⚠️ ZMIEŃ HASŁO ADMINISTRATORA po pierwszym logowaniu!';
    } else {
        $msg[] = 'ℹ️ W bazie jest ' . $userCount . ' użytkowników — konto admina nie tworzone';
    }

} catch (Exception $e) {
    $ok = false;
    $msg[] = '❌ BŁĄD: ' . $e->getMessage();
}
?><!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalacja / Migracja — SCK Harmonogram</title>
<style>
body{font-family:system-ui;max-width:600px;margin:40px auto;padding:0 20px;background:#fafaf9;color:#1c1917}
h1{color:#ea580c}
.log{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:20px;margin:20px 0}
.log div{padding:6px 0;border-bottom:1px solid #f5f5f4;font-size:14px}
.log div:last-child{border:none}
.ok{background:#f0fdf4;border-color:#22c55e;padding:16px;border-radius:10px;margin:20px 0;font-weight:600;color:#16a34a}
.err{background:#fef2f2;border-color:#dc2626;padding:16px;border-radius:10px;margin:20px 0;font-weight:600;color:#dc2626}
.warn{background:#fffbeb;border:1px solid #fbbf24;border-radius:10px;padding:16px;margin:20px 0;font-size:13px;color:#92400e}
.safe{background:#f0fdf4;border:1px solid #22c55e;border-radius:10px;padding:12px 16px;margin:20px 0;font-size:13px;color:#166534}
a{color:#ea580c}
</style></head><body>
<h1>📅 Instalacja / Migracja SCK Harmonogram</h1>
<div class="safe">🛡️ Ten skrypt jest bezpieczny — nigdy nie usuwa ani nie nadpisuje istniejących danych. Tworzy tylko brakujące tabele i kolumny.</div>
<div class="log"><?php foreach($msg as $m):?><div><?=$m?></div><?php endforeach;?></div>
<?php if($ok):?>
<div class="ok">✅ Migracja zakończona pomyślnie!</div>
<div class="warn">
    <strong>⚠️ WAŻNE — wykonaj po instalacji:</strong><br><br>
    1. <strong>Usuń plik install.php</strong> z serwera<br>
    2. Zaloguj się: <a href="login.php">→ Logowanie</a>
</div>
<?php else:?>
<div class="err">❌ Migracja nie powiodła się. Sprawdź dane w includes/config.php</div>
<?php endif;?>
</body></html>
