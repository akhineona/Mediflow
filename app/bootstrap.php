<?php

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

$configPath = ROOT_PATH . '/config/config.php';
if (!is_file($configPath)) {
    $target = 'setup.php';
    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }
    throw new RuntimeException('MediFlow is not installed. Run setup.php first.');
}

$GLOBALS['mediflow_config'] = require $configPath;

require_once ROOT_PATH . '/app/helpers.php';
require_once ROOT_PATH . '/app/db.php';
require_once ROOT_PATH . '/app/auth.php';

$timezone = (string) config('app.timezone', 'Asia/Dhaka');
date_default_timezone_set($timezone);

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$sessionName = (string) config('security.session_name', 'mediflow_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$timeout = (int) config('security.session_timeout', 7200);
if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $timeout) {
    logout_user();
    session_start();
    flash('auth', 'Your session expired. Please sign in again.', 'warning');
    header('Location: ' . route_url('login'));
    exit;
}
$_SESSION['last_activity'] = time();

if ((bool) config('app.debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

set_exception_handler(static function (Throwable $e): void {
    $line = sprintf("[%s] %s in %s:%d\n%s\n\n", date(DATE_ATOM), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents(ROOT_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ((bool) config('app.debug', false)) {
        echo '<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Application error</title><style>body{font-family:Arial;background:#f5f7fb;padding:40px;color:#172033}.box{max-width:620px;margin:auto;background:#fff;padding:28px;border-radius:16px;box-shadow:0 15px 40px #0001}h1{margin-top:0}a{color:#2563eb}</style></head><body><div class="box"><h1>Something went wrong</h1><p>The request could not be completed. The technical details were written to <code>storage/logs/app.log</code>.</p><p><a href="index.php">Return to MediFlow</a></p></div></body></html>';
    }
});
