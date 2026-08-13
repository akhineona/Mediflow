<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'index.php','setup.php','api.php','download.php','database/schema.sql','database/seed.php',
    'app/bootstrap.php','app/db.php','app/helpers.php','app/auth.php',
    'assets/css/app.css','assets/js/app.js','pages/dashboard.php','pages/appointments.php',
    'pages/consultations.php','pages/labs.php','pages/billing.php','README.md',
    'tools/static_qa.php','documentation/INSTALLATION.md','documentation/TESTING.md','documentation/QA_REPORT.md'
];
$failed = false;
foreach ($required as $file) {
    $ok = is_file($root . '/' . $file);
    printf("[%s] %s\n", $ok ? 'OK' : 'MISSING', $file);
    if (!$ok) $failed = true;
}
foreach (['config','storage','uploads','uploads/lab_reports'] as $dir) {
    $ok = is_dir($root . '/' . $dir) && is_writable($root . '/' . $dir);
    printf("[%s] writable %s/\n", $ok ? 'OK' : 'FAIL', $dir);
    if (!$ok) $failed = true;
}
$schema = file_get_contents($root . '/database/schema.sql') ?: '';
$tableCount = preg_match_all('/CREATE TABLE\s+/i', $schema);
$viewCount = preg_match_all('/CREATE OR REPLACE VIEW\s+/i', $schema);
$statementCount = substr_count($schema, '-- statement-break') + 1;
printf("[INFO] statements=%d tables=%d views=%d\n", $statementCount, $tableCount, $viewCount);
if ($statementCount < 50 || $tableCount < 20 || $viewCount < 1) $failed = true;
exit($failed ? 1 : 0);
