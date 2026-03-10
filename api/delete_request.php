<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u) json_out(['error'=>'Unauthorized'], 401);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'POST only'], 405);
if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf']??'',$_POST['_token'])) json_out(['error'=>'CSRF'], 403);

$id = (int)($_POST['request_id'] ?? 0);
if (!$id) json_out(['error'=>'Missing ID'], 400);

$db = get_db();
$stmt = $db->prepare('SELECT * FROM form_requests WHERE id=?');
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) json_out(['error'=>'Not found'], 404);

// Only own requests can be deleted by employee
if ($req['user_id'] != $u['id'] && $u['role'] !== 'admin' && $u['role'] !== 'kadry') {
    json_out(['error'=>'Forbidden'], 403);
}

// Delete PDF file if exists
if ($req['pdf_file']) {
    $filepath = __DIR__ . '/../storage/requests/' . $req['pdf_file'];
    if (file_exists($filepath)) @unlink($filepath);
}

$db->prepare('DELETE FROM form_requests WHERE id=?')->execute([$id]);
json_out(['ok'=>true]);
