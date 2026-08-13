<?php

declare(strict_types=1);

function current_user(bool $refresh = false): ?array
{
    static $cached = null;
    static $loaded = false;
    if ($refresh) {
        $cached = null;
        $loaded = false;
    }
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }
    $stmt = db()->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? LIMIT 1');
    $stmt->execute([(int) $userId]);
    $user = $stmt->fetch();
    if (!$user || $user['account_status'] !== 'Active') {
        unset($_SESSION['user_id']);
        return null;
    }
    $cached = $user;
    return $cached;
}

function attempt_login(string $identity, string $password): bool
{
    $stmt = db()->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE (u.email = ? OR u.username = ?) LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();
    if (!$user || $user['account_status'] !== 'Active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['last_activity'] = time();
    db()->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')->execute([$user['user_id']]);
    current_user(true);
    log_action('LOGIN', 'users', (int) $user['user_id']);
    return true;
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        log_action('LOGOUT', 'users', (int) $_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    current_user(true);
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function role_name(): ?string
{
    return current_user()['role_name'] ?? null;
}

function has_role(string|array $roles): bool
{
    $roles = (array) $roles;
    return in_array(role_name(), $roles, true);
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('auth', 'Please sign in to continue.', 'warning');
        redirect_to('login');
    }
}

function require_roles(string|array $roles): void
{
    require_login();
    if (!has_role($roles)) {
        http_response_code(403);
        require ROOT_PATH . '/pages/forbidden.php';
        exit;
    }
}

function can_view_patient(int $patientId): bool
{
    if (has_role(['Admin', 'Receptionist'])) {
        return true;
    }
    if (has_role('Patient')) {
        $patient = patient_for_current_user();
        return $patient && (int) $patient['patient_id'] === $patientId;
    }
    if (has_role('Doctor')) {
        $doctor = doctor_for_current_user();
        if (!$doctor) {
            return false;
        }
        $stmt = db()->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND doctor_id = ?');
        $stmt->execute([$patientId, $doctor['doctor_id']]);
        return (int) $stmt->fetchColumn() > 0;
    }
    if (has_role('Lab Operator')) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM lab_requests WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        return (int) $stmt->fetchColumn() > 0;
    }
    return false;
}
