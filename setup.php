<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('mediflow_setup');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
if (empty($_SESSION['_setup_csrf'])) {
    $_SESSION['_setup_csrf'] = bin2hex(random_bytes(32));
}

$root = __DIR__;
$configFile = $root . '/config/config.php';
$lockFile = $root . '/storage/setup.lock';
$message = null;
$error = null;
$accounts = [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function detected_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mediflow/setup.php')), '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

$installed = is_file($configFile) && is_file($lockFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $postedToken = (string) ($_POST['_csrf'] ?? '');
        if ($postedToken === '' || !hash_equals((string) $_SESSION['_setup_csrf'], $postedToken)) {
            throw new RuntimeException('The setup security token expired. Refresh the page and try again.');
        }
        $dbHost = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
        $dbPort = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim((string) ($_POST['db_name'] ?? 'mediflow'));
        $dbUser = trim((string) ($_POST['db_user'] ?? 'root'));
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        $baseUrl = rtrim(trim((string) ($_POST['base_url'] ?? detected_base_url())), '/');
        $timezone = trim((string) ($_POST['timezone'] ?? 'Asia/Dhaka'));
        $adminName = trim((string) ($_POST['admin_name'] ?? 'Administrator'));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? 'admin@mediflow.local'));
        $adminUsername = trim((string) ($_POST['admin_username'] ?? 'admin'));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
        $installDemo = isset($_POST['install_demo']);
        $confirmReset = isset($_POST['confirm_reset']);

        if ($installed) {
            throw new RuntimeException('Setup is locked after installation. To reinstall intentionally, delete storage/setup.lock and config/config.php first.');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new RuntimeException('Database name may contain only letters, numbers and underscores.');
        }
        if ($dbPort < 1 || $dbPort > 65535) {
            throw new RuntimeException('Database port must be between 1 and 65535.');
        }
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid application URL, for example http://localhost/mediflow.');
        }
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException('Enter a valid PHP timezone, for example Asia/Dhaka.');
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid administrator email address.');
        }
        if (strlen($adminPassword) < 8) {
            throw new RuntimeException('Administrator password must be at least 8 characters.');
        }
        if (!hash_equals($adminPassword, $adminPasswordConfirm)) {
            throw new RuntimeException('Administrator password confirmation does not match.');
        }
        if ($adminName === '' || $adminUsername === '') {
            throw new RuntimeException('Administrator name and username are required.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $adminUsername)) {
            throw new RuntimeException('Administrator username must be 3–80 characters and may contain letters, numbers, dots, underscores and hyphens.');
        }

        foreach ([$root . '/config', $root . '/storage', $root . '/storage/logs', $root . '/uploads/lab_reports', $root . '/uploads/profiles'] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Could not create required directory: ' . $dir);
            }
            if (!is_writable($dir)) {
                throw new RuntimeException('Directory is not writable: ' . $dir);
            }
        }

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);
        $serverPdo = new PDO($serverDsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $schemaCountStmt = $serverPdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
        $schemaCountStmt->execute([$dbName]);
        $existingTableCount = (int) $schemaCountStmt->fetchColumn();
        if ($existingTableCount > 0 && !$confirmReset) {
            throw new RuntimeException('The selected database already contains tables. Check the replacement confirmation box only if you intend to reinstall MediFlow.');
        }
        $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $schema = file_get_contents($root . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Could not read database/schema.sql.');
        }
        foreach (explode('-- statement-break', $schema) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $pdo->beginTransaction();
        try {
            $roleStmt = $pdo->prepare('INSERT INTO roles (role_name, description) VALUES (?, ?)');
            $roleDefinitions = [
                ['Admin', 'Full system administration'],
                ['Receptionist', 'Patient registration, appointments, queue and billing'],
                ['Doctor', 'Consultations, prescriptions and clinical records'],
                ['Lab Operator', 'Laboratory request processing and reports'],
                ['Patient', 'Self-service appointments and personal records'],
            ];
            foreach ($roleDefinitions as $role) {
                $roleStmt->execute($role);
            }

            if ($installDemo) {
                require_once $root . '/database/seed.php';
                $seedResult = seed_demo_data($pdo);
                $accounts = $seedResult['accounts'];

                $adminRole = (int) $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Admin'")->fetchColumn();
                $existingAdminId = (int) $pdo->query("SELECT user_id FROM users WHERE username = 'admin'")->fetchColumn();
                $duplicateAdminStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id <> ? AND (username = ? OR email = ?)');
                $duplicateAdminStmt->execute([$existingAdminId, $adminUsername, $adminEmail]);
                if ((int) $duplicateAdminStmt->fetchColumn() > 0) {
                    throw new RuntimeException('The chosen administrator username or email conflicts with a demo account. Choose a different value.');
                }
                $stmt = $pdo->prepare('UPDATE users SET full_name = ?, username = ?, email = ?, password_hash = ?, role_id = ? WHERE user_id = ?');
                $stmt->execute([$adminName, $adminUsername, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminRole, $existingAdminId]);
                foreach ($accounts as &$account) {
                    if ($account['role'] === 'Admin') {
                        $account['email'] = $adminEmail;
                        $account['password'] = $adminPassword;
                    }
                }
                unset($account);
            } else {
                $adminRole = (int) $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Admin'")->fetchColumn();
                $stmt = $pdo->prepare('INSERT INTO users (role_id, full_name, username, email, password_hash, account_status) VALUES (?, ?, ?, ?, ?, \'Active\')');
                $stmt->execute([$adminRole, $adminName, $adminUsername, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
                $adminId = (int) $pdo->lastInsertId();
                $defaults = [
                    ['hospital_name', 'MediFlow Hospital', 'Displayed hospital name'],
                    ['hospital_phone', '', 'Hospital contact number'],
                    ['hospital_address', '', 'Hospital address'],
                    ['currency_symbol', '৳', 'Currency symbol'],
                    ['appointment_cancellation_hours', '4', 'Minimum hours before cancellation'],
                    ['queue_refresh_seconds', '30', 'Queue refresh interval'],
                ];
                $stmt = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, description, updated_by) VALUES (?, ?, ?, ?)');
                foreach ($defaults as $setting) {
                    $stmt->execute([$setting[0], $setting[1], $setting[2], $adminId]);
                }
                $accounts = [['role' => 'Admin', 'email' => $adminEmail, 'password' => $adminPassword]];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $config = [
            'app' => [
                'name' => 'MediFlow',
                'base_url' => $baseUrl,
                'timezone' => $timezone,
                'debug' => false,
            ],
            'db' => [
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass,
                'charset' => 'utf8mb4',
            ],
            'security' => [
                'session_name' => 'mediflow_session',
                'session_timeout' => 7200,
            ],
        ];

        $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
            throw new RuntimeException('Could not write config/config.php. Check folder permissions.');
        }
        if (file_put_contents($lockFile, 'Installed at ' . date(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
            @unlink($configFile);
            throw new RuntimeException('Could not create storage/setup.lock. Check folder permissions.');
        }
        $installed = true;
        $message = 'MediFlow was installed successfully.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$requirements = [
    ['PHP 8.1+', version_compare(PHP_VERSION, '8.1.0', '>=')],
    ['PDO extension', extension_loaded('pdo')],
    ['PDO MySQL extension', extension_loaded('pdo_mysql')],
    ['Fileinfo extension', extension_loaded('fileinfo')],
    ['Config directory writable', is_writable($root . '/config')],
    ['Storage directory writable', is_writable($root . '/storage')],
    ['Uploads directory writable', is_writable($root . '/uploads')],
];
$ready = !in_array(false, array_column($requirements, 1), true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediFlow Setup</title>
    <style>
        :root{--blue:#2563eb;--navy:#172033;--bg:#f4f7fb;--muted:#667085;--ok:#15803d;--bad:#b42318;--line:#e4e7ec}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:linear-gradient(135deg,#eef6ff,#f8fbff 48%,#eefcf8);color:var(--navy);min-height:100vh;padding:32px}
        .wrap{max-width:1040px;margin:auto}.hero{display:flex;align-items:center;gap:18px;margin-bottom:24px}.logo{width:58px;height:58px;border-radius:17px;background:var(--blue);color:#fff;display:grid;place-items:center;font-size:30px;box-shadow:0 12px 30px #2563eb33}.hero h1{margin:0;font-size:30px}.hero p{margin:6px 0 0;color:var(--muted)}
        .grid{display:grid;grid-template-columns:360px 1fr;gap:24px}.card{background:#fff;border:1px solid #ffffffaa;border-radius:20px;box-shadow:0 18px 45px #14213d12;padding:24px}.card h2{margin-top:0;font-size:20px}.req{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--line)}.req:last-child{border:0}.yes{color:var(--ok);font-weight:700}.no{color:var(--bad);font-weight:700}
        .alert{padding:14px 16px;border-radius:12px;margin-bottom:16px}.success{background:#ecfdf3;color:#166534}.error{background:#fff1f2;color:#9f1239}.installed{background:#eff6ff;color:#1d4ed8}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin-bottom:14px}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:7px}.field input{width:100%;padding:11px 12px;border:1px solid #d0d5dd;border-radius:10px;font:inherit;outline:none}.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px #2563eb1a}.check{display:flex;gap:10px;align-items:flex-start;margin:12px 0 18px;color:var(--muted);font-size:14px}.check input{margin-top:3px}.btn{width:100%;border:0;border-radius:11px;padding:13px 16px;background:var(--blue);color:#fff;font-weight:800;font-size:15px;cursor:pointer;transition:.2s}.btn:hover{transform:translateY(-1px);box-shadow:0 10px 24px #2563eb35}.btn:disabled{opacity:.55;cursor:not-allowed;transform:none}.muted{color:var(--muted);font-size:13px}.accounts{margin-top:16px;border:1px solid var(--line);border-radius:12px;overflow:hidden}.accounts table{width:100%;border-collapse:collapse}.accounts th,.accounts td{padding:10px;text-align:left;border-bottom:1px solid var(--line);font-size:13px}.accounts tr:last-child td{border:0}.link{display:inline-block;margin-top:15px;color:var(--blue);font-weight:700;text-decoration:none}@media(max-width:850px){body{padding:18px}.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero"><div class="logo">✚</div><div><h1>MediFlow Setup</h1><p>Configure the XAMPP database, create tables and prepare the first administrator account.</p></div></div>
    <div class="grid">
        <aside class="card">
            <h2>System check</h2>
            <?php foreach ($requirements as [$label, $ok]): ?>
                <div class="req"><span><?= h($label) ?></span><span class="<?= $ok ? 'yes' : 'no' ?>"><?= $ok ? 'Ready' : 'Missing' ?></span></div>
            <?php endforeach; ?>
            <p class="muted">For XAMPP, start both Apache and MySQL before running setup. The default MySQL user is usually <strong>root</strong> with a blank password.</p>
            <?php if ($installed): ?><div class="alert installed">An installation lock already exists. Reinstalling will erase current MediFlow tables.</div><?php endif; ?>
        </aside>
        <main class="card">
            <h2>Installation details</h2>
            <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

            <?php if ($message): ?>
                <p>The application is ready. Save the credentials below before continuing.</p>
                <?php if ($accounts): ?><div class="accounts"><table><thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead><tbody><?php foreach ($accounts as $account): ?><tr><td><?= h($account['role']) ?></td><td><?= h($account['email']) ?></td><td><?= h($account['password']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                <a class="link" href="index.php">Open MediFlow →</a>
            <?php elseif ($installed): ?>
                <p>Setup cannot modify the installed database.</p>
                <a class="link" href="index.php">Open MediFlow →</a>
            <?php else: ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= h((string) $_SESSION['_setup_csrf']) ?>">
                <div class="row"><div class="field"><label>Database host</label><input name="db_host" value="<?= h($_POST['db_host'] ?? '127.0.0.1') ?>" required></div><div class="field"><label>Database port</label><input name="db_port" type="number" min="1" max="65535" value="<?= h((string)($_POST['db_port'] ?? '3306')) ?>" required></div></div>
                <div class="row"><div class="field"><label>Database name</label><input name="db_name" value="<?= h($_POST['db_name'] ?? 'mediflow') ?>" required></div><div class="field"><label>Database user</label><input name="db_user" value="<?= h($_POST['db_user'] ?? 'root') ?>" required></div></div>
                <div class="field"><label>Database password</label><input name="db_pass" type="password" autocomplete="current-password"></div>
                <div class="row"><div class="field"><label>Application URL</label><input name="base_url" value="<?= h($_POST['base_url'] ?? detected_base_url()) ?>" required></div><div class="field"><label>Timezone</label><input name="timezone" value="<?= h($_POST['timezone'] ?? 'Asia/Dhaka') ?>" required></div></div>
                <div class="row"><div class="field"><label>Administrator name</label><input name="admin_name" value="<?= h($_POST['admin_name'] ?? 'Administrator') ?>" required></div><div class="field"><label>Administrator username</label><input name="admin_username" value="<?= h($_POST['admin_username'] ?? 'admin') ?>" required></div></div>
                <div class="field"><label>Administrator email</label><input name="admin_email" type="email" value="<?= h($_POST['admin_email'] ?? 'admin@mediflow.local') ?>" required></div>
                <div class="row"><div class="field"><label>Administrator password</label><input name="admin_password" type="password" minlength="8" autocomplete="new-password" required><div class="muted">At least 8 characters.</div></div><div class="field"><label>Confirm password</label><input name="admin_password_confirm" type="password" minlength="8" autocomplete="new-password" required></div></div>
                <label class="check"><input type="checkbox" name="install_demo" value="1" <?= isset($_POST['install_demo']) || !$_POST ? 'checked' : '' ?>><span>Install realistic demo data and accounts for every role.</span></label>
                <label class="check"><input type="checkbox" name="confirm_reset" value="1" <?= isset($_POST['confirm_reset']) ? 'checked' : '' ?>><span>I understand that this should be a dedicated MediFlow database. Existing MediFlow tables with matching names will be replaced during installation. Leave this unchecked for a new, empty database.</span></label>
                <button class="btn" type="submit" <?= $ready ? '' : 'disabled' ?>>Install MediFlow</button>
            </form>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
