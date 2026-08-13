<?php
$error = null;
if (request_method('POST')) {
    try {
        verify_csrf();
        $identity = post_string('identity');
        $password = (string) ($_POST['password'] ?? '');
        if ($identity === '' || $password === '') {
            throw new RuntimeException('Enter your email or username and password.');
        }
        if (!attempt_login($identity, $password)) {
            throw new RuntimeException('The credentials are incorrect or the account is inactive.');
        }
        redirect_to('dashboard');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$authFlash = flash('auth');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · MediFlow</title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
.auth-page{min-height:100vh;display:grid;grid-template-columns:1.1fr .9fr;background:#f5f8fc}.auth-visual{position:relative;overflow:hidden;background:linear-gradient(145deg,#0f2b55,#155e75);padding:60px;color:#fff;display:flex;flex-direction:column;justify-content:space-between}.auth-visual:before,.auth-visual:after{content:"";position:absolute;border-radius:50%;background:#ffffff0d}.auth-visual:before{width:440px;height:440px;right:-180px;top:-120px}.auth-visual:after{width:320px;height:320px;left:-150px;bottom:-120px}.auth-brand{display:flex;align-items:center;gap:13px;position:relative}.auth-brand .brand-mark{width:50px;height:50px}.auth-copy{position:relative;max-width:620px}.auth-copy h1{font-size:48px;line-height:1.05;margin:0 0 18px}.auth-copy p{color:#d5e7f7;font-size:17px}.feature-list{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:30px}.feature{background:#ffffff0d;border:1px solid #ffffff1a;border-radius:13px;padding:14px}.feature strong{display:block}.feature span{font-size:12px;color:#bfdbfe}.auth-form-wrap{display:grid;place-items:center;padding:35px}.auth-card{width:min(450px,100%);background:#fff;border-radius:20px;padding:34px;box-shadow:0 24px 60px #17203318}.auth-card h2{font-size:27px;margin:0 0 6px}.auth-card>p{color:var(--muted);margin:0 0 25px}.auth-footer{text-align:center;color:var(--muted);font-size:13px;margin-top:18px}.demo-note{font-size:12px;background:#eff6ff;color:#1d4ed8;padding:11px;border-radius:10px;margin:15px 0}@media(max-width:850px){.auth-page{grid-template-columns:1fr}.auth-visual{display:none}.auth-form-wrap{min-height:100vh;padding:18px}.auth-card{padding:25px}}
</style></head>
<body>
<div class="auth-page">
<section class="auth-visual">
  <div class="auth-brand"><div class="brand-mark">✚</div><div><strong style="font-size:22px">MediFlow</strong><div style="color:#bfdbfe;font-size:12px">Hospital workflow, simplified</div></div></div>
  <div class="auth-copy"><h1>Better patient flow.<br>Clearer clinical records.</h1><p>A complete outpatient management system for appointments, queues, consultations, prescriptions, laboratory requests and billing.</p><div class="feature-list"><div class="feature"><strong>Safe records</strong><span>Role-based access and audit history</span></div><div class="feature"><strong>Smart queue</strong><span>Tokens and waiting-time visibility</span></div><div class="feature"><strong>Clinical workflow</strong><span>Consultation to prescription and lab</span></div><div class="feature"><strong>Simple billing</strong><span>Invoices, payments and due tracking</span></div></div></div>
  <small style="position:relative;color:#9ec5e9">Designed for local XAMPP deployment.</small>
</section>
<section class="auth-form-wrap">
  <div class="auth-card">
    <h2>Welcome back</h2><p>Sign in with your MediFlow account.</p>
    <?php if ($authFlash): ?><div class="alert alert-<?= e($authFlash['type']) ?>"><?= e($authFlash['message']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label class="required">Email or username</label><input class="input" name="identity" value="<?= e($_POST['identity'] ?? '') ?>" autocomplete="username" required autofocus></div>
      <div class="field"><label class="required">Password</label><div class="input-group"><input class="input" id="loginPassword" name="password" type="password" autocomplete="current-password" required><button class="btn btn-light" type="button" data-password-toggle="loginPassword">Show</button></div></div>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <div class="auth-footer">New patient? <a href="<?= e(route_url('register')) ?>" style="color:var(--primary);font-weight:750">Create a patient account</a><br><span>Administrator setup: <a href="<?= e(url('setup.php')) ?>">setup.php</a></span></div>
  </div>
</section>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
