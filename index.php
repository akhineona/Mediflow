<?php

declare(strict_types=1);

if (!is_file(__DIR__ . '/config/config.php')) {
    header('Location: setup.php');
    exit;
}

require __DIR__ . '/app/bootstrap.php';

$page = get_string('page', is_logged_in() ? 'dashboard' : 'login');

if ($page === 'logout') {
    logout_user();
    session_start();
    flash('auth', 'You have been signed out.', 'success');
    redirect_to('login');
}

$publicPages = ['login', 'register'];
if (!is_logged_in() && !in_array($page, $publicPages, true)) {
    $page = 'login';
}
if (is_logged_in() && in_array($page, ['login', 'register'], true)) {
    redirect_to('dashboard');
}

$routes = [
    'login' => ['file' => 'login.php', 'roles' => []],
    'register' => ['file' => 'register.php', 'roles' => []],
    'dashboard' => ['file' => 'dashboard.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Lab Operator', 'Patient']],
    'profile' => ['file' => 'profile.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Lab Operator', 'Patient']],
    'patients' => ['file' => 'patients.php', 'roles' => ['Admin', 'Receptionist', 'Doctor']],
    'departments' => ['file' => 'departments.php', 'roles' => ['Admin']],
    'doctors' => ['file' => 'doctors.php', 'roles' => ['Admin', 'Receptionist', 'Patient']],
    'schedules' => ['file' => 'schedules.php', 'roles' => ['Admin', 'Doctor']],
    'appointments' => ['file' => 'appointments.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Patient']],
    'queue' => ['file' => 'queue.php', 'roles' => ['Admin', 'Receptionist', 'Doctor']],
    'consultations' => ['file' => 'consultations.php', 'roles' => ['Admin', 'Doctor']],
    'prescriptions' => ['file' => 'prescriptions.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Patient']],
    'labs' => ['file' => 'labs.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Lab Operator', 'Patient']],
    'billing' => ['file' => 'billing.php', 'roles' => ['Admin', 'Receptionist', 'Patient']],
    'notifications' => ['file' => 'notifications.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Lab Operator', 'Patient']],
    'reports' => ['file' => 'reports.php', 'roles' => ['Admin', 'Receptionist', 'Doctor']],
    'users' => ['file' => 'users.php', 'roles' => ['Admin']],
    'audit' => ['file' => 'audit.php', 'roles' => ['Admin']],
    'catalogs' => ['file' => 'catalogs.php', 'roles' => ['Admin']],
    'settings' => ['file' => 'settings.php', 'roles' => ['Admin']],
    'print-prescription' => ['file' => 'print_prescription.php', 'roles' => ['Admin', 'Receptionist', 'Doctor', 'Patient'], 'print' => true],
    'print-invoice' => ['file' => 'print_invoice.php', 'roles' => ['Admin', 'Receptionist', 'Patient'], 'print' => true],
];

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'not_found';
    $route = ['file' => 'not_found.php', 'roles' => []];
} else {
    $route = $routes[$page];
}

if (!in_array($page, $publicPages, true) && $page !== 'not_found') {
    require_roles($route['roles']);
}

if (in_array($page, $publicPages, true) || !empty($route['print'])) {
    require ROOT_PATH . '/pages/' . $route['file'];
    exit;
}

$pageTitle = ucwords(str_replace('-', ' ', $page));
require ROOT_PATH . '/partials/header.php';
require ROOT_PATH . '/partials/sidebar.php';
require ROOT_PATH . '/pages/' . $route['file'];
require ROOT_PATH . '/partials/footer.php';
