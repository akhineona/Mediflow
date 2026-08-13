<?php
$error = null;
if (request_method('POST')) {
    try {
        verify_csrf();
        $name = post_string('full_name');
        $email = strtolower(post_string('email'));
        $username = strtolower(post_string('username'));
        $phone = post_string('phone');
        $dob = post_string('date_of_birth');
        $gender = post_string('gender');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $username === '' || $phone === '') {
            throw new RuntimeException('Complete all required fields with valid information.');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username)) {
            throw new RuntimeException('Username must be 3–40 characters using letters, numbers, dots, underscores or hyphens.');
        }
        if (strlen($password) < 8 || $password !== $confirm) {
            throw new RuntimeException('Passwords must match and contain at least 8 characters.');
        }
        if ($dob !== '' && (!validate_date($dob) || $dob > date('Y-m-d'))) {
            throw new RuntimeException('Enter a valid date of birth.');
        }
        $pdo = db();
        $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR username = ?');
        $dup->execute([$email, $username]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new RuntimeException('The email or username is already registered.');
        }
        $patientDup = $pdo->prepare("SELECT patient_code FROM patients WHERE (phone = ? AND phone <> '') OR (email = ? AND email <> '') LIMIT 1");
        $patientDup->execute([$phone, $email]);
        if ($existingPatientCode = $patientDup->fetchColumn()) {
            throw new RuntimeException('A hospital patient record already exists for this phone or email (' . $existingPatientCode . '). Ask reception to verify the record before creating portal access.');
        }
        $pdo->beginTransaction();
        $roleId = (int) query_value("SELECT role_id FROM roles WHERE role_name = 'Patient'");
        $stmt = $pdo->prepare("INSERT INTO users (role_id, full_name, username, email, password_hash, phone, account_status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([$roleId, $name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
        $userId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO patients (user_id, full_name, date_of_birth, gender, phone, email, registration_type, registered_by) VALUES (?, ?, ?, ?, ?, ?, \'Normal\', ?)');
        $stmt->execute([$userId, $name, nullable($dob), nullable($gender), $phone, $email, $userId]);
        $patientId = (int) $pdo->lastInsertId();
        $patientCode = code_from_id('PAT', $patientId);
        $pdo->prepare('UPDATE patients SET patient_code = ? WHERE patient_id = ?')->execute([$patientCode, $patientId]);
        $pdo->commit();
        log_action('SELF_REGISTER', 'patients', $patientId, null, ['patient_code' => $patientCode]);
        flash('auth', 'Registration completed. You can now sign in.', 'success');
        redirect_to('login');
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Patient registration · MediFlow</title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><style>body{background:linear-gradient(145deg,#eff6ff,#f8fafc,#ecfdf5);min-height:100vh}.register-wrap{max-width:760px;margin:auto;padding:34px 18px}.register-card{background:#fff;border-radius:20px;padding:28px;box-shadow:var(--shadow)}.register-head{display:flex;align-items:center;gap:13px;margin-bottom:23px}.register-head h1{margin:0;font-size:26px}.register-head p{margin:3px 0 0;color:var(--muted)}.back{display:inline-block;margin-top:18px;color:var(--primary);font-weight:750;text-decoration:none}</style></head><body><div class="register-wrap"><div class="register-card"><div class="register-head"><div class="brand-mark">✚</div><div><h1>Create patient account</h1><p>Register to book appointments and view personal records.</p></div></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><div class="form-grid"><div class="field"><label class="required">Full name</label><input class="input" name="full_name" value="<?=e($_POST['full_name']??'')?>" required></div><div class="field"><label class="required">Phone</label><input class="input" name="phone" value="<?=e($_POST['phone']??'')?>" required></div><div class="field"><label class="required">Email</label><input class="input" type="email" name="email" value="<?=e($_POST['email']??'')?>" required></div><div class="field"><label class="required">Username</label><input class="input" name="username" value="<?=e($_POST['username']??'')?>" required></div><div class="field"><label>Date of birth</label><input class="input" type="date" max="<?=date('Y-m-d')?>" name="date_of_birth" value="<?=e($_POST['date_of_birth']??'')?>"></div><div class="field"><label>Gender</label><select class="select" name="gender"><option value="">Select</option><?php foreach(['Female','Male','Other','Prefer not to say'] as $g):?><option <?=($_POST['gender']??'')===$g?'selected':''?>><?=e($g)?></option><?php endforeach;?></select></div><div class="field"><label class="required">Password</label><input class="input" type="password" name="password" minlength="8" required></div><div class="field"><label class="required">Confirm password</label><input class="input" type="password" name="password_confirmation" minlength="8" required></div></div><button class="btn btn-primary btn-block" type="submit">Create account</button></form><a class="back" href="<?=e(route_url('login'))?>">← Back to sign in</a></div></div></body></html>
