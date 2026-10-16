<?php
session_start();
require_once '../config/db-config.php';

// Only allow logged-in admins
if(!isset($_SESSION['admin'])) {
    http_response_code(403);
    die("Access denied.");
}

$type    = $_GET['type']    ?? '';
$user_id = (int)($_GET['user_id'] ?? 0);

if(!$user_id || !in_array($type, ['id', 'address'])) {
    http_response_code(400);
    die("Invalid request.");
}

$stmt = $pdo->prepare("SELECT id_document, address_document FROM users WHERE id=? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user) {
    http_response_code(404);
    die("User not found.");
}

$file_path = $type === 'id' ? $user['id_document'] : $user['address_document'];

if(empty($file_path) || !file_exists($file_path)) {
    http_response_code(404);
    die("Document not found.");
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf',
];

$mime = $mime_types[$ext] ?? 'application/octet-stream';

header("Content-Type: {$mime}");
header("Content-Disposition: inline; filename=\"document.{$ext}\"");
header("Content-Length: " . filesize($file_path));
header("X-Content-Type-Options: nosniff");

readfile($file_path);
exit;