<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_login();

$type = get_string('type');
$id = (int) ($_GET['id'] ?? 0);

if ($type !== 'lab' || $id < 1) {
    http_response_code(404);
    exit('File not found.');
}

$stmt = db()->prepare('SELECT lri.report_file_path, lr.patient_id FROM lab_request_items lri JOIN lab_requests lr ON lr.lab_request_id = lri.lab_request_id WHERE lri.request_item_id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row || !$row['report_file_path'] || !can_view_patient((int) $row['patient_id'])) {
    http_response_code(403);
    exit('File not found or access denied.');
}

$relative = ltrim(str_replace('\\', '/', (string) $row['report_file_path']), '/');
$allowedDirectory = realpath(ROOT_PATH . '/uploads/lab_reports');
$file = realpath(ROOT_PATH . '/' . $relative);
if ($allowedDirectory === false || $file === false || !str_starts_with($file, $allowedDirectory . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Stored file is missing.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file) ?: 'application/octet-stream';
$extension = pathinfo($file, PATHINFO_EXTENSION);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="lab-report-' . $id . '.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
