<?php

declare(strict_types=1);

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['mediflow_config'] ?? [];
    if ($key === null || $key === '') {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function route_url(string $page, array $params = []): string
{
    return url('index.php?' . http_build_query(array_merge(['page' => $page], $params)));
}

function redirect_to(string $page, array $params = []): never
{
    header('Location: ' . route_url($page, $params));
    exit;
}

function redirect_url(string $target): never
{
    header('Location: ' . $target);
    exit;
}

function flash(string $key, ?string $message = null, string $type = 'info'): mixed
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = ['message' => $message, 'type' => $type];
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function all_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('The security token expired. Refresh the page and try again.');
    }
}

function request_method(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === strtoupper($method);
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function get_string(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function nullable(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function code_from_id(string $prefix, int $id, bool $withYear = true): string
{
    return $prefix . '-' . ($withYear ? date('Y') . '-' : '') . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function appointment_code(int $id): string
{
    return 'APT-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function lab_request_code(int $id): string
{
    return 'LAB-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function invoice_code(int $id): string
{
    return 'INV-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function payment_code(int $id): string
{
    return 'PAY-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function prescription_code(int $id): string
{
    return 'RX-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function money(float|int|string|null $amount): string
{
    $symbol = setting('currency_symbol', '৳');
    return $symbol . number_format((float) $amount, 2);
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $time = strtotime($date);
    return $time === false ? $date : date($format, $time);
}

function format_time(?string $time, string $format = 'h:i A'): string
{
    if (!$time) {
        return '—';
    }
    $timestamp = strtotime($time);
    return $timestamp === false ? $time : date($format, $timestamp);
}

function status_class(string $status): string
{
    $status = strtolower(trim($status));
    return match (true) {
        in_array($status, ['active', 'paid', 'completed', 'confirmed', 'resolved'], true) => 'success',
        in_array($status, ['pending', 'requested', 'processing', 'waiting', 'checked in', 'partial', 'partially paid', 'recommended'], true) => 'warning',
        in_array($status, ['cancelled', 'blocked', 'inactive', 'no-show', 'failed', 'unpaid'], true) => 'danger',
        in_array($status, ['in consultation', 'sample collected', 'draft'], true) => 'info',
        default => 'neutral',
    };
}

function setting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value === false ? $default : $value;
    } catch (Throwable) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function log_action(string $action, ?string $table = null, int|string|null $recordId = null, mixed $oldData = null, mixed $newData = null): void
{
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action_type, table_name, record_id, old_data, new_data, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $action,
            $table,
            $recordId === null ? null : (string) $recordId,
            $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        @file_put_contents(ROOT_PATH . '/storage/logs/audit-errors.log', '[' . date(DATE_ATOM) . '] ' . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function notify_user(?int $userId, string $title, string $message, string $type = 'Info', ?string $page = null, ?int $recordId = null): void
{
    if (!$userId) {
        return;
    }

    try {
        $stmt = db()->prepare('INSERT INTO notifications (user_id, title, message, notification_type, related_page, related_record_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $message, $type, $page, $recordId]);
    } catch (Throwable $e) {
        // Notifications are secondary side effects and must never roll back or
        // misreport a successful clinical, billing, or appointment operation.
        @file_put_contents(
            ROOT_PATH . '/storage/logs/notification-errors.log',
            '[' . date(DATE_ATOM) . '] ' . $e->getMessage() . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

function recalculate_invoice(int $invoiceId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) FROM invoice_items WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    $subtotal = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT discount_amount, tax_amount FROM invoices WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        return;
    }

    $total = max(0, $subtotal - (float) $invoice['discount_amount'] + (float) $invoice['tax_amount']);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND payment_status = 'Completed'");
    $stmt->execute([$invoiceId]);
    $paid = (float) $stmt->fetchColumn();
    $due = max(0, $total - $paid);
    $status = $paid <= 0 ? 'Unpaid' : ($due > 0 ? 'Partially Paid' : 'Paid');

    $stmt = $pdo->prepare('UPDATE invoices SET subtotal = ?, total_amount = ?, paid_amount = ?, due_amount = ?, payment_status = ? WHERE invoice_id = ?');
    $stmt->execute([$subtotal, $total, $paid, $due, $status, $invoiceId]);
}

function find_patient_user_id(int $patientId): ?int
{
    $stmt = db()->prepare('SELECT user_id FROM patients WHERE patient_id = ?');
    $stmt->execute([$patientId]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? null : (int) $value;
}

function find_doctor_user_id(int $doctorId): ?int
{
    $stmt = db()->prepare('SELECT user_id FROM doctors WHERE doctor_id = ?');
    $stmt->execute([$doctorId]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? null : (int) $value;
}

function patient_for_current_user(): ?array
{
    $user = current_user();
    if (!$user || $user['role_name'] !== 'Patient') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM patients WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user['user_id']]);
    return $stmt->fetch() ?: null;
}

function doctor_for_current_user(): ?array
{
    $user = current_user();
    if (!$user || $user['role_name'] !== 'Doctor') {
        return null;
    }
    $stmt = db()->prepare('SELECT d.*, u.full_name, u.email, u.phone FROM doctors d JOIN users u ON u.user_id = d.user_id WHERE d.user_id = ? LIMIT 1');
    $stmt->execute([$user['user_id']]);
    return $stmt->fetch() ?: null;
}

function validate_date(string $date, string $format = 'Y-m-d'): bool
{
    $dt = DateTime::createFromFormat($format, $date);
    return $dt && $dt->format($format) === $date;
}

function uploaded_file(string $field, string $subdirectory, array $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'], int $maxBytes = 5242880): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The uploaded file could not be processed.');
    }
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('The uploaded file exceeds the 5 MB limit.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('Only PDF, JPG and PNG files are allowed.');
    }
    $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $directory = ROOT_PATH . '/uploads/' . trim($subdirectory, '/');
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Upload directory could not be created.');
    }
    $target = $directory . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('The uploaded file could not be saved.');
    }
    return 'uploads/' . trim($subdirectory, '/') . '/' . $filename;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function query_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function day_name(int $day): string
{
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day] ?? 'Unknown';
}
