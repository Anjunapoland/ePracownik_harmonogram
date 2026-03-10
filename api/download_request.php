<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$u = current_user();
if (!$u) { http_response_code(401); die('Unauthorized'); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Missing ID'); }

$db = get_db();
$stmt = $db->prepare('SELECT * FROM form_requests WHERE id=?');
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { http_response_code(404); die('Not found'); }

// Access: own request, or admin/kadry role
if ($req['user_id'] != $u['id'] && $u['role'] !== 'admin' && $u['role'] !== 'kadry') {
    http_response_code(403); die('Forbidden');
}

if (!$req['pdf_file']) { http_response_code(404); die('No PDF'); }

$filepath = __DIR__ . '/../storage/requests/' . $req['pdf_file'];
if (!file_exists($filepath)) { http_response_code(404); die('File not found'); }

$fileHead = (string)file_get_contents($filepath, false, null, 0, 5);
if ($fileHead !== '%PDF-') {
    $decider = '';
    if (!empty($req['decided_by'])) {
        $stDecider = $db->prepare('SELECT full_name FROM users WHERE id=?');
        $stDecider->execute([(int)$req['decided_by']]);
        $decider = (string)($stDecider->fetchColumn() ?: 'Administrator');
    }
    $newFile = generate_request_pdf($req, $decider ?: 'Administrator');
    $filepath = __DIR__ . '/../storage/requests/' . $newFile;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="wniosek_' . $req['id'] . '.pdf"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
