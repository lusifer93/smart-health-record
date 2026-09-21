<?php

declare(strict_types=1);

/** Escape values used by shared layout components. */
function layout_h(mixed $value): string
{
    return htmlspecialchars($value === null ? '' : (string)$value, ENT_QUOTES, 'UTF-8');
}

function layout_initials(?array $profile, ?array $account): string
{
    $name = trim((string)($profile['full_name'] ?? $account['email'] ?? 'User'));
    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = strtoupper(substr((string)($parts[0] ?? 'U'), 0, 1));
    if (count($parts) > 1) $initials .= strtoupper(substr((string)end($parts), 0, 1));
    return substr($initials ?: 'U', 0, 2);
}

function layout_nav_items(?string $role): array
{
    if ($role === 'doctor') {
        return [
            ['doctor_dashboard', 'Overview', 'bi-grid-1x2'],
            ['doctor_patients', 'My patients', 'bi-people'],
            ['doctor_records', 'Shared records', 'bi-folder2-open'],
            ['doctor_appointments', 'Appointments', 'bi-calendar3'],
            ['doctor_notes', 'Clinical notes', 'bi-journal-medical'],
            ['doctor_profile', 'My profile', 'bi-person-badge'],
            ['doctor_verification', 'Verification', 'bi-patch-check'],
            ['doctor_security', 'Security', 'bi-shield-lock'],
        ];
    }
    if ($role === 'admin') {
        return [
            ['admin_dashboard', 'Overview', 'bi-grid-1x2'],
            ['admin_verification', 'Doctor verification', 'bi-patch-check'],
        ];
    }
    return [
        ['dashboard', 'Overview', 'bi-grid-1x2'],
        ['metrics', 'Vitals & trends', 'bi-activity'],
        ['records', 'Medical records', 'bi-folder2-open'],
        ['medicine_manage', 'My medicines', 'bi-capsule'],
        ['appointments', 'Appointments', 'bi-calendar3'],
        ['sharing', 'Sharing & access', 'bi-share'],
        ['reports', 'Health insights', 'bi-stars'],
        ['security', 'Security', 'bi-shield-lock'],
    ];
}

function head(string $title): void
{
    $account = user();
    $profile = $account ? (profile() ?? []) : [];
    $role = $account ? (current_role() ?? 'patient') : null;
    $page = (string)($_GET['page'] ?? 'dashboard');
    $isDoctor = $role === 'doctor';
    $isAdmin = $role === 'admin';
    $theme = $isDoctor ? 'theme-doctor' : ($isAdmin ? 'theme-admin' : 'theme-patient');
    $navItems = layout_nav_items($role);
    $notificationCount = (int)($_SESSION['notification_count'] ?? 0);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= layout_h($title) ?> · CareNest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --app-ink:#183247; --app-muted:#6f8190; --app-bg:#f5f8fa; --app-surface:#fff;
            --app-line:#e5edf1; --app-primary:#1f9d91; --app-primary-dark:#15766f;
            --app-soft:#e9f8f5; --app-shadow:0 10px 28px rgba(24,50,71,.07); --app-radius:14px;
        }
        .theme-doctor { --app-primary:#24558a; --app-primary-dark:#173b65; --app-soft:#eaf2fb; --app-bg:#f3f7fb; }
        .theme-admin { --app-primary:#7655b8; --app-primary-dark:#55398e; --app-soft:#f1edfb; --app-bg:#f7f6fb; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--app-ink); background:var(--app-bg); font-family:'DM Sans',sans-serif; font-size:14px; }
        h1,h2,h3,h4,h5,h6,.brand { font-family:'Plus Jakarta Sans',sans-serif; letter-spacing:-.02em; }
        a { color:var(--app-primary); text-decoration:none; } a:hover { color:var(--app-primary-dark); }
        .app-shell { min-height:100vh; display:flex; }
        .app-sidebar { width:236px; flex:0 0 236px; background:var(--app-surface); border-right:1px solid var(--app-line); display:flex; flex-direction:column; padding:20px 14px; position:fixed; inset:0 auto 0 0; z-index:1030; }
        .brand { display:flex; align-items:center; gap:10px; color:var(--app-ink); font-size:18px; font-weight:800; padding:4px 10px 24px; }
        .brand:hover { color:var(--app-ink); }.brand-mark { width:32px; height:32px; border-radius:10px; display:grid; place-items:center; color:#fff; background:var(--app-primary); font-size:20px; font-weight:700; box-shadow:0 5px 12px color-mix(in srgb,var(--app-primary) 24%,transparent); }
        .nav-label { color:#9aa9b3; font-size:10px; font-weight:700; letter-spacing:.12em; padding:8px 12px; text-transform:uppercase; }
        .app-nav { display:flex; flex-direction:column; gap:3px; }.app-nav a { display:flex; align-items:center; gap:11px; color:#71818d; border-radius:10px; padding:10px 12px; font-weight:600; transition:background .18s,color .18s,transform .18s; }.app-nav a i { width:18px; font-size:16px; text-align:center; }.app-nav a:hover { color:var(--app-primary); background:var(--app-soft); transform:translateX(2px); }.app-nav a.active { color:var(--app-primary-dark); background:var(--app-soft); }
        .sidebar-bottom { margin-top:auto; border-top:1px solid var(--app-line); padding-top:14px; }.sidebar-bottom a { color:var(--app-muted); display:flex; align-items:center; gap:10px; padding:9px 12px; font-weight:600; }
        .app-content { min-width:0; width:calc(100% - 236px); margin-left:236px; }.app-header { height:70px; display:flex; align-items:center; justify-content:space-between; padding:0 clamp(18px,3vw,38px); background:rgba(255,255,255,.86); border-bottom:1px solid var(--app-line); backdrop-filter:blur(14px); position:sticky; top:0; z-index:1020; }.header-title { font-size:15px; font-weight:700; }.header-actions { display:flex; align-items:center; gap:12px; }.icon-button { width:36px; height:36px; display:grid; place-items:center; border:1px solid var(--app-line); border-radius:10px; color:var(--app-muted); background:var(--app-surface); position:relative; }.icon-button:hover { color:var(--app-primary); border-color:var(--app-primary); }.notification-dot { position:absolute; top:5px; right:5px; width:7px; height:7px; background:#e15d67; border:2px solid #fff; border-radius:50%; }.profile-trigger { display:flex; align-items:center; gap:9px; color:var(--app-ink); font-weight:600; }.profile-trigger:hover { color:var(--app-primary); }.avatar { width:34px; height:34px; display:grid; place-items:center; border-radius:11px; color:var(--app-primary-dark); background:var(--app-soft); font-size:12px; font-weight:800; }.app-main { padding:28px clamp(18px,3vw,38px) 44px; max-width:1500px; margin:0 auto; }.page-heading { margin-bottom:24px; }.page-heading h1 { font-size:24px; margin:0 0 5px; font-weight:800; }.page-heading p { color:var(--app-muted); margin:0; }.eyebrow { color:var(--app-primary); font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; }.card,.glass-card,.doctor-card { border:1px solid var(--app-line)!important; border-radius:var(--app-radius)!important; box-shadow:var(--app-shadow); background:var(--app-surface)!important; }.card { transition:box-shadow .18s,transform .18s; }.card:hover { box-shadow:0 14px 32px rgba(24,50,71,.1); }.metric { color:var(--app-ink); font-family:'Plus Jakarta Sans',sans-serif; font-size:28px; font-weight:800; margin:5px 0; }.btn { border-radius:10px; font-weight:700; padding:.58rem .85rem; font-size:13px; }.btn-primary { --bs-btn-bg:var(--app-primary); --bs-btn-border-color:var(--app-primary); --bs-btn-hover-bg:var(--app-primary-dark); --bs-btn-hover-border-color:var(--app-primary-dark); }.btn-outline-primary { --bs-btn-color:var(--app-primary); --bs-btn-border-color:var(--app-primary); --bs-btn-hover-bg:var(--app-primary); --bs-btn-hover-border-color:var(--app-primary); }.form-control,.form-select { border-color:var(--app-line); border-radius:10px; padding:.65rem .75rem; font-size:13px; }.form-control:focus,.form-select:focus { border-color:var(--app-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--app-primary) 15%,transparent); }.form-label { color:#526572; font-size:12px; font-weight:700; margin-bottom:6px; }.badge,.role-chip,.tag { border-radius:999px; font-size:11px; font-weight:700; padding:.35rem .6rem; }.role-chip,.tag { color:var(--app-primary-dark); background:var(--app-soft); }.table { --bs-table-bg:transparent; font-size:13px; }.table > :not(caption) > * > * { border-color:var(--app-line); padding:.8rem .55rem; }.empty-state { text-align:center; padding:32px 18px; color:var(--app-muted); }.empty-state i { display:block; color:var(--app-primary); font-size:28px; margin-bottom:10px; }.dropdown-menu { border:1px solid var(--app-line); border-radius:12px; box-shadow:var(--app-shadow); padding:7px; }.dropdown-item { border-radius:8px; font-size:13px; padding:8px 10px; }.dropdown-item:active { background:var(--app-soft); color:var(--app-ink); }.alert { border-radius:12px; font-size:13px; }.auth-page { min-height:calc(100vh - 30px); display:grid; place-items:center; padding:24px 0; }.auth-hero { border-radius:14px 0 0 14px; background:linear-gradient(145deg,var(--app-primary-dark),var(--app-primary)); }.auth-wrap { overflow:hidden; border-radius:16px; background:#fff; max-width:960px; width:100%; }.mobile-toggle { display:none; }
        @media (max-width:991.98px) { .app-sidebar { transform:translateX(-100%); transition:transform .2s; box-shadow:var(--app-shadow); }.app-sidebar.open { transform:translateX(0); }.app-content { width:100%; margin-left:0; }.mobile-toggle { display:grid; }.app-header { height:62px; }.profile-name { display:none; } }
        @media (max-width:575.98px) { .app-main { padding:20px 14px 32px; }.page-heading h1 { font-size:21px; }.header-actions { gap:7px; }.auth-hero { border-radius:14px 14px 0 0; }.auth-wrap { margin:0 12px; } }
    </style>
</head>
<body class="<?= layout_h($theme) ?>">
<?php if ($account): ?>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a class="brand" href="?"><span class="brand-mark"><i class="bi bi-plus-lg"></i></span>CareNest</a>
        <div class="nav-label"><?= $isDoctor ? 'Clinician workspace' : ($isAdmin ? 'Administration' : 'Your health space') ?></div>
        <nav class="app-nav" aria-label="Primary navigation">
            <?php foreach ($navItems as [$key, $label, $icon]): ?>
                <a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= layout_h($key) ?>"><i class="bi <?= layout_h($icon) ?>"></i><span><?= layout_h($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom"><a href="?action=logout"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></a></div>
    </aside>
    <div class="app-content">
        <header class="app-header">
            <div class="d-flex align-items-center gap-3"><button class="icon-button mobile-toggle" type="button" aria-label="Open navigation" onclick="document.getElementById('appSidebar').classList.toggle('open')"><i class="bi bi-list"></i></button><div class="header-title"><?= layout_h($title) ?></div></div>
            <div class="header-actions">
                <a class="icon-button" href="?page=<?= $isDoctor ? 'doctor_dashboard' : ($isAdmin ? 'admin_dashboard' : 'dashboard') ?>" aria-label="Notifications"><i class="bi bi-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-dot"></span><?php endif; ?></a>
                <div class="dropdown"><a class="profile-trigger" href="#" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar"><?= layout_h(layout_initials($profile, $account)) ?></span><span class="profile-name"><?= layout_h($profile['full_name'] ?? $account['email'] ?? 'Account') ?></span><i class="bi bi-chevron-down small"></i></a><ul class="dropdown-menu dropdown-menu-end"><li><div class="px-2 py-1 small text-muted"><?= layout_h($account['email'] ?? '') ?></div></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="?page=<?= $isDoctor ? 'doctor_profile' : ($isAdmin ? 'admin_dashboard' : 'security') ?>"><i class="bi bi-person me-2"></i>Account</a></li><li><a class="dropdown-item" href="?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li></ul></div>
            </div>
        </header>
<?php else: ?>
<main class="container py-3">
<?php endif; ?>
<?php foreach ($_SESSION['flash'] ?? [] as $flash): ?><div class="alert alert-<?= layout_h($flash['type'] ?? 'info') ?> alert-dismissible fade show border-0 shadow-sm mb-3" role="alert"><?= layout_h($flash['message'] ?? '') ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endforeach; unset($_SESSION['flash']); ?>
<?php if ($account): ?><main class="app-main">
<?php endif;
}

function foot(): void
{
    $account = user();
    if ($account) echo '</main></div></div>';
    else echo '</main>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
