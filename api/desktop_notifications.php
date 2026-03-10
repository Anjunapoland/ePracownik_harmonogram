<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_response($payload, $status)
{
    http_response_code((int)$status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function include_if_exists($path)
{
    if (is_file($path)) {
        require_once $path;
    }
}

function arr_get($array, $key, $default = null)
{
    return (is_array($array) && array_key_exists($key, $array)) ? $array[$key] : $default;
}

function str_contains_compat($haystack, $needle)
{
    if ($needle === '') {
        return true;
    }
    return mb_strpos((string)$haystack, (string)$needle) !== false;
}

$root = dirname(__DIR__);
include_if_exists($root . '/includes/config.php');
include_if_exists($root . '/includes/db.php');
include_if_exists($root . '/includes/auth.php');
include_if_exists($root . '/includes/layout.php');

function get_pdo_connection()
{
    if (function_exists('get_db')) {
        $db = get_db();
        if ($db instanceof PDO) {
            return $db;
        }
    }

    $globalNames = array('pdo', 'db', 'conn', 'connection');
    foreach ($globalNames as $globalName) {
        if (isset($GLOBALS[$globalName]) && $GLOBALS[$globalName] instanceof PDO) {
            return $GLOBALS[$globalName];
        }
    }

    $host = null;
    $name = null;
    $user = null;
    $pass = null;
    $charset = 'utf8mb4';

    $constantMap = array(
        'DB_HOST' => 'host',
        'DB_NAME' => 'name',
        'DB_USER' => 'user',
        'DB_PASS' => 'pass',
        'DB_PASSWORD' => 'pass',
        'DB_CHARSET' => 'charset',
        'MYSQL_HOST' => 'host',
        'MYSQL_DATABASE' => 'name',
        'MYSQL_USER' => 'user',
        'MYSQL_PASSWORD' => 'pass',
    );

    foreach ($constantMap as $const => $target) {
        if (defined($const)) {
            ${$target} = constant($const);
        }
    }

    $envMap = array(
        'DB_HOST' => 'host',
        'DB_NAME' => 'name',
        'DB_USER' => 'user',
        'DB_PASS' => 'pass',
        'DB_PASSWORD' => 'pass',
        'DB_CHARSET' => 'charset',
    );

    foreach ($envMap as $env => $target) {
        $value = getenv($env);
        if ($value !== false && $value !== '') {
            ${$target} = $value;
        }
    }

    if (!$host || !$name || !$user) {
        json_response(array(
            'ok' => false,
            'error' => 'Brak połączenia z bazą danych. Sprawdź includes/config.php lub funkcję get_db().'
        ), 500);
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset ? $charset : 'utf8mb4');

    return new PDO($dsn, (string)$user, (string)$pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
}

function detect_logged_user_id($pdo)
{
    if (function_exists('current_user')) {
        $user = current_user();
        if (is_array($user) && isset($user['id'])) {
            return (int)$user['id'];
        }
    }

    $candidates = array(
        isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        isset($_SESSION['uid']) ? $_SESSION['uid'] : null,
        isset($_SESSION['auth_user_id']) ? $_SESSION['auth_user_id'] : null,
        isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : null,
        isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null,
        isset($_SESSION['auth']) && is_array($_SESSION['auth']) && isset($_SESSION['auth']['id']) ? $_SESSION['auth']['id'] : null,
    );

    foreach ($candidates as $candidate) {
        if (is_numeric($candidate) && (int)$candidate > 0) {
            return (int)$candidate;
        }
    }

    $emailCandidates = array(
        isset($_SESSION['email']) ? $_SESSION['email'] : null,
        isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null,
        isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : null,
        isset($_SESSION['auth']) && is_array($_SESSION['auth']) && isset($_SESSION['auth']['email']) ? $_SESSION['auth']['email'] : null,
    );

    foreach ($emailCandidates as $email) {
        if (!is_string($email) || trim($email) === '') {
            continue;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND active = 1 LIMIT 1');
        $stmt->execute(array('email' => trim($email)));
        $row = $stmt->fetch();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
    }

    return null;
}

function base_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return $scheme . '://' . $host;
}

function map_notification_url($row)
{
    $type = mb_strtolower((string)arr_get($row, 'type', 'info'));
    $title = mb_strtolower((string)arr_get($row, 'title', ''));
    $body = mb_strtolower((string)arr_get($row, 'body', ''));
    $haystack = $type . ' ' . $title . ' ' . $body;

    $root = base_url();

    if (str_contains_compat($haystack, 'harmonogram') || str_contains_compat($haystack, 'grafik') || str_contains_compat($haystack, 'schedule')) {
        return $root . '/schedule.php';
    }

    if (
        str_contains_compat($haystack, 'urlop') ||
        str_contains_compat($haystack, 'wniosek') ||
        str_contains_compat($haystack, 'podanie') ||
        str_contains_compat($haystack, 'nadgodzin')
    ) {
        return $root . '/dashboard.php';
    }

    if (
        str_contains_compat($haystack, 'ogłosz') ||
        str_contains_compat($haystack, 'announcement') ||
        str_contains_compat($haystack, 'komunikat')
    ) {
        return $root . '/dashboard.php';
    }

    return $root . '/dashboard.php';
}

function normalize_notification_type($type, $title, $body)
{
    $haystack = mb_strtolower(trim((string)$type . ' ' . (string)$title . ' ' . (string)$body));

    if (str_contains_compat($haystack, 'harmonogram') || str_contains_compat($haystack, 'grafik') || str_contains_compat($haystack, 'schedule')) {
        return 'schedule';
    }
    if (str_contains_compat($haystack, 'ogłosz') || str_contains_compat($haystack, 'announcement') || str_contains_compat($haystack, 'komunikat')) {
        return 'announcement';
    }
    if (str_contains_compat($haystack, 'urlop') || str_contains_compat($haystack, 'wniosek') || str_contains_compat($haystack, 'podanie')) {
        return 'leave_request';
    }
    if (str_contains_compat($haystack, 'nadgodzin')) {
        return 'overtime';
    }
    return 'info';
}

try {
    $pdo = get_pdo_connection();
    $userId = detect_logged_user_id($pdo);

    if (!$userId) {
        json_response(array(
            'ok' => false,
            'error' => 'Brak zalogowanego użytkownika.'
        ), 401);
    }

    $limit = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? max(1, min(20, (int)$_GET['limit'])) : 10;
    $onlyUnread = (!isset($_GET['all']) || $_GET['all'] !== '1');
    $sinceId = (isset($_GET['since_id']) && is_numeric($_GET['since_id'])) ? (int)$_GET['since_id'] : 0;
    $markRead = (isset($_GET['mark_read']) && $_GET['mark_read'] === '1');

    $sql = "
        SELECT id, type, title, body, related_date, is_read, created_at
        FROM notifications
        WHERE user_id = :user_id
    ";

    $params = array('user_id' => $userId);

    if ($onlyUnread) {
        $sql .= " AND is_read = 0";
    }

    if ($sinceId > 0) {
        $sql .= " AND id > :since_id";
        $params['since_id'] = $sinceId;
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    $items = array();
    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int)$row['id'];
        $title = trim((string)arr_get($row, 'title', ''));
        $body = trim((string)arr_get($row, 'body', ''));
        $type = normalize_notification_type((string)arr_get($row, 'type', 'info'), $title, $body);

        $items[] = array(
            'id' => (string)$row['id'],
            'type' => $type,
            'title' => $title !== '' ? $title : 'ePracownik',
            'body' => $body !== '' ? $body : 'Masz nowe powiadomienie w systemie.',
            'url' => map_notification_url($row),
            'related_date' => arr_get($row, 'related_date', null),
            'is_read' => (int)arr_get($row, 'is_read', 0),
            'created_at' => arr_get($row, 'created_at', null),
        );
    }

    if ($markRead && !empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND id IN ($placeholders)");
        $bind = array_merge(array($userId), $ids);
        $update->execute($bind);
    }

    json_response(array(
        'ok' => true,
        'user_id' => $userId,
        'count' => count($items),
        'items' => $items,
    ), 200);

} catch (Exception $e) {
    json_response(array(
        'ok' => false,
        'error' => 'Błąd endpointu desktop_notifications.php',
        'details' => $e->getMessage(),
    ), 500);
}
