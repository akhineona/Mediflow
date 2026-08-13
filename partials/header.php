<?php
$user = current_user();
$unreadCount = 0;
if ($user) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user['user_id']]);
    $unreadCount = (int) $stmt->fetchColumn();
}
$hospitalName = setting('hospital_name', 'MediFlow Hospital');
$currentPage = get_string('page', 'dashboard');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle) ?> · <?= e(config('app.name', 'MediFlow')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
<div id="mobileOverlay" class="mobile-overlay"></div>
