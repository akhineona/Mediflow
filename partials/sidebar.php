<?php
$role = role_name();
$menus = [
    ['section' => 'Workspace'],
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => '⌂', 'roles' => ['Admin','Receptionist','Doctor','Lab Operator','Patient']],
    ['page' => 'appointments', 'label' => 'Appointments', 'icon' => '◷', 'roles' => ['Admin','Receptionist','Doctor','Patient']],
    ['page' => 'queue', 'label' => 'Patient Queue', 'icon' => '≡', 'roles' => ['Admin','Receptionist','Doctor']],
    ['page' => 'consultations', 'label' => 'Consultations', 'icon' => '✚', 'roles' => ['Admin','Doctor']],
    ['page' => 'prescriptions', 'label' => 'Prescriptions', 'icon' => '✎', 'roles' => ['Admin','Receptionist','Doctor','Patient']],
    ['page' => 'labs', 'label' => 'Laboratory', 'icon' => '⚗', 'roles' => ['Admin','Receptionist','Doctor','Lab Operator','Patient']],
    ['page' => 'billing', 'label' => 'Billing', 'icon' => '৳', 'roles' => ['Admin','Receptionist','Patient']],
    ['section' => 'Management'],
    ['page' => 'patients', 'label' => 'Patients', 'icon' => '♙', 'roles' => ['Admin','Receptionist','Doctor']],
    ['page' => 'doctors', 'label' => 'Doctors', 'icon' => '⚕', 'roles' => ['Admin','Receptionist','Patient']],
    ['page' => 'departments', 'label' => 'Departments', 'icon' => '▦', 'roles' => ['Admin']],
    ['page' => 'schedules', 'label' => 'Schedules', 'icon' => '▤', 'roles' => ['Admin','Doctor']],
    ['page' => 'reports', 'label' => 'Reports', 'icon' => '◫', 'roles' => ['Admin','Receptionist','Doctor']],
    ['page' => 'catalogs', 'label' => 'Clinical Catalogs', 'icon' => '▧', 'roles' => ['Admin']],
    ['section' => 'System'],
    ['page' => 'users', 'label' => 'Users & Roles', 'icon' => '♧', 'roles' => ['Admin']],
    ['page' => 'audit', 'label' => 'Audit Logs', 'icon' => '⌁', 'roles' => ['Admin']],
    ['page' => 'settings', 'label' => 'Settings', 'icon' => '⚙', 'roles' => ['Admin']],
    ['page' => 'notifications', 'label' => 'Notifications', 'icon' => '◉', 'roles' => ['Admin','Receptionist','Doctor','Lab Operator','Patient']],
    ['page' => 'profile', 'label' => 'My Profile', 'icon' => '◎', 'roles' => ['Admin','Receptionist','Doctor','Lab Operator','Patient']],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">✚</div>
        <div class="brand-copy"><strong>MediFlow</strong><span><?= e($hospitalName) ?></span></div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menus as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section"><?= e($item['section']) ?></div>
            <?php elseif (in_array($role, $item['roles'], true)): ?>
                <a class="nav-link <?= $currentPage === $item['page'] ? 'active' : '' ?>" href="<?= e(route_url($item['page'])) ?>" title="<?= e($item['label']) ?>">
                    <span class="nav-icon"><?= e($item['icon']) ?></span><span class="nav-label"><?= e($item['label']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
        <div class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></div>
        <div class="sidebar-user-copy"><strong><?= e($user['full_name'] ?? '') ?></strong><span><?= e($role ?? '') ?></span></div>
    </div>
</aside>
<div class="main-wrap">
    <header class="topbar">
        <div class="topbar-left">
            <button class="icon-btn" id="mobileMenuToggle" type="button" aria-label="Open navigation">☰</button>
            <button class="icon-btn" id="sidebarToggle" type="button" aria-label="Collapse navigation">⇤</button>
            <div class="page-title"><h1><?= e($pageTitle) ?></h1><p><?= e($hospitalName) ?></p></div>
        </div>
        <div class="topbar-right">
            <a class="icon-btn" href="<?= e(route_url('notifications')) ?>" aria-label="Notifications">◉<?php if ($unreadCount): ?><span class="notification-count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?></a>
            <a class="user-chip" href="<?= e(route_url('profile')) ?>"><span class="avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?></span><strong><?= e($user['full_name'] ?? '') ?></strong></a>
            <a class="icon-btn" href="<?= e(route_url('logout')) ?>" title="Sign out">↪</a>
        </div>
    </header>
    <main class="content">
