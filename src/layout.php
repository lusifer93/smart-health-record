<?php

declare(strict_types=1);

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

function head(string $title): void
{
    $account = user();
    $profile = $account ? (profile() ?? []) : [];
    $role = $account ? (current_role() ?? 'patient') : null;
    $isDoctor = $role === 'doctor';
    $isAdmin = $role === 'admin';
    $home = $isDoctor ? 'doctor_dashboard' : ($isAdmin ? 'admin_dashboard' : 'dashboard');
    $theme = $isDoctor ? 'theme-doctor' : ($isAdmin ? 'theme-admin' : 'theme-patient');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= layout_h($title) ?> · CareNest</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--ink:#213737;--muted:#7b8986;--bg:#f7f8f3;--surface:#fffefa;--line:#e7ebe3;--accent:#8aa68c;--accent-dark:#587a61;--soft:#edf3e9;--shadow:0 18px 50px rgba(60,82,65,.08);--radius:20px}
.theme-doctor{--accent:#748fa8;--accent-dark:#49657f;--soft:#eaf0f5;--bg:#f6f8f9}.theme-admin{--accent:#9b86b5;--accent-dark:#70577f;--soft:#f1edf5;--bg:#faf8fb}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 85% 0%,var(--soft),transparent 30%),var(--bg);color:var(--ink);font:14px 'DM Sans',sans-serif}h1,h2,h3,h4,h5,h6,.brand{font-family:Manrope,sans-serif;letter-spacing:-.03em}a{color:var(--accent-dark);text-decoration:none}a:hover{color:var(--ink)}
.app-content{min-height:100vh}.app-header{height:82px;display:flex;align-items:center;justify-content:space-between;padding:0 clamp(20px,5vw,80px);background:rgba(255,254,250,.78);border-bottom:1px solid rgba(231,235,227,.9);backdrop-filter:blur(18px);position:sticky;top:0;z-index:1020}.brand{display:flex;align-items:center;gap:11px;color:var(--ink);font-size:20px;font-weight:800}.brand:hover{color:var(--ink)}.brand-mark{width:37px;height:37px;border-radius:14px;display:grid;place-items:center;background:var(--accent-dark);color:#fff;box-shadow:0 8px 18px color-mix(in srgb,var(--accent-dark) 20%,transparent)}.header-context{display:flex;align-items:center;gap:20px}.header-page{color:var(--muted);font-size:13px;font-weight:600;padding-left:20px;border-left:1px solid var(--line)}.header-actions{display:flex;align-items:center;gap:11px}.icon-button{width:39px;height:39px;display:grid;place-items:center;border:1px solid var(--line);border-radius:13px;background:var(--surface);color:var(--muted);position:relative}.icon-button:hover{color:var(--accent-dark);border-color:var(--accent)}.notification-dot{position:absolute;top:6px;right:6px;width:7px;height:7px;border:2px solid var(--surface);border-radius:50%;background:#db7c70}.profile-trigger{display:flex;align-items:center;gap:9px;color:var(--ink);font-weight:700}.profile-trigger:hover{color:var(--accent-dark)}.avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:13px;background:var(--soft);color:var(--accent-dark);font-size:11px;font-weight:800}.app-main{max-width:1440px;margin:0 auto;padding:38px clamp(20px,5vw,80px) 58px}.page-heading{margin-bottom:27px}.page-heading h1{font-size:26px;margin:0 0 6px;font-weight:800}.page-heading p{color:var(--muted);margin:0}.card,.glass-card,.doctor-card{border:1px solid var(--line)!important;border-radius:var(--radius)!important;background:rgba(255,254,250,.9)!important;box-shadow:var(--shadow)}.card{transition:transform .2s,box-shadow .2s}.card:hover{box-shadow:0 24px 55px rgba(60,82,65,.12)}.metric{font:800 30px Manrope;color:var(--ink);margin:6px 0}.eyebrow{color:var(--accent-dark);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.btn{border-radius:12px;font-size:13px;font-weight:700;padding:.65rem .95rem}.btn-primary{--bs-btn-bg:var(--accent-dark);--bs-btn-border-color:var(--accent-dark);--bs-btn-hover-bg:var(--ink);--bs-btn-hover-border-color:var(--ink)}.btn-outline-primary{--bs-btn-color:var(--accent-dark);--bs-btn-border-color:var(--accent);--bs-btn-hover-bg:var(--accent-dark);--bs-btn-hover-border-color:var(--accent-dark)}.form-control,.form-select{border-color:var(--line);border-radius:12px;padding:.7rem .8rem;font-size:13px;background:var(--surface)}.form-control:focus,.form-select:focus{border-color:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 15%,transparent)}.form-label{color:#667674;font-size:12px;font-weight:700;margin-bottom:6px}.role-chip,.tag,.badge{display:inline-block;border-radius:999px;padding:.38rem .68rem;background:var(--soft);color:var(--accent-dark);font-size:11px;font-weight:800}.table{--bs-table-bg:transparent;font-size:13px}.table>:not(caption)>*>*{border-color:var(--line);padding:.9rem .6rem}.empty-state{text-align:center;color:var(--muted);padding:38px 18px}.empty-state i{display:block;color:var(--accent-dark);font-size:29px;margin-bottom:11px}.dropdown-menu{border:1px solid var(--line);border-radius:15px;box-shadow:var(--shadow);padding:7px}.dropdown-item{border-radius:10px;font-size:13px;padding:9px 10px}.dropdown-item:active{background:var(--soft);color:var(--ink)}.alert{border:0;border-radius:14px;font-size:13px}.side-nav{border-radius:18px!important;background:rgba(255,254,250,.74)!important}.side-nav a{display:flex;align-items:center;gap:9px;border-radius:11px;color:var(--muted);font-weight:600;padding:10px}.side-nav a:hover,.side-nav a.active{background:var(--soft);color:var(--accent-dark)}
.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:34px 18px;background:linear-gradient(135deg,#f5f7ef 0%,#fbfcf8 54%,#eef4ed 100%)}
/* Bootstrap's .row/.col rules are intentionally neutralised here: the auth page uses its own fixed two-column composition. */
.auth-wrap,.auth-wrap.row{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;gap:0!important;overflow:hidden;width:min(930px,calc(100vw - 36px))!important;max-width:930px!important;min-height:560px;margin:0 auto!important;border:1px solid rgba(219,228,217,.95);border-radius:28px;background:var(--surface);box-shadow:0 26px 70px rgba(59,81,62,.14)!important}
.auth-wrap>.auth-hero,.auth-wrap>.auth-panel,.auth-wrap>.col-md-6{width:auto!important;max-width:none!important;flex:none!important;min-width:0!important;margin:0!important}
.auth-hero{display:flex;flex-direction:column;justify-content:center;padding:56px 52px;background:linear-gradient(145deg,#648773 0%,#88a98e 100%);border-radius:0!important}.auth-hero h1{max-width:390px;margin:14px 0 18px;color:#18372d;font-size:clamp(34px,4vw,52px);line-height:1.03;font-weight:800}.auth-hero p.lead{max-width:380px;margin:0;color:rgba(255,255,255,.78)!important;font-size:16px;line-height:1.55}.auth-hero .eyebrow{color:rgba(255,255,255,.72)}.mini-feature{max-width:330px;margin-top:30px;color:#17372d;font-size:14px;line-height:1.45}.mini-feature+.mini-feature{margin-top:15px}.mini-feature strong{font-weight:800}.mini-feature .small{color:rgba(255,255,255,.72)!important}.auth-panel{display:flex;flex-direction:column;justify-content:center;padding:56px 58px;background:rgba(255,254,250,.96)}.auth-panel>.brand{margin-bottom:42px}.auth-panel h2{margin:10px 0!important;font-family:Manrope!important;font-size:32px!important;line-height:1.12;color:var(--ink)}.auth-panel>div>p{font-size:14px}.auth-panel .form-control{height:50px}.auth-panel .btn{height:50px}.auth-panel .d-flex{gap:18px}.auth-panel .d-flex a{white-space:nowrap}
@media(max-width:767.98px){.auth-page{padding:18px 12px}.auth-wrap,.auth-wrap.row{display:flex!important;flex-direction:column;min-height:0;width:100%!important;border-radius:22px}.auth-hero{padding:34px 28px}.auth-hero h1{font-size:36px}.mini-feature{margin-top:20px}.auth-panel{padding:34px 28px}.auth-panel>.brand{margin-bottom:28px}.auth-panel h2{font-size:27px!important}.auth-panel .d-flex{flex-wrap:wrap;justify-content:space-between}}
</style></head>
<body class="<?= layout_h($theme) ?>">
<?php if ($account): ?>
<div class="app-content"><header class="app-header"><div class="header-context"><a class="brand" href="?"><span class="brand-mark"><i class="bi bi-plus-lg"></i></span>CareNest</a><span class="header-page"><?= layout_h($title) ?></span></div><div class="header-actions"><a class="icon-button" href="?page=<?= layout_h($home) ?>" aria-label="Notifications"><i class="bi bi-bell"></i><?php if (!empty($_SESSION['notification_count'])): ?><span class="notification-dot"></span><?php endif; ?></a><div class="dropdown"><a class="profile-trigger" href="#" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar"><?= layout_h(layout_initials($profile,$account)) ?></span><span class="profile-name"><?= layout_h($profile['full_name'] ?? $account['email'] ?? 'Account') ?></span><i class="bi bi-chevron-down small"></i></a><ul class="dropdown-menu dropdown-menu-end"><li><div class="px-2 py-1 small text-muted"><?= layout_h($account['email'] ?? '') ?></div></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="?page=<?= $isDoctor ? 'doctor_profile' : ($isAdmin ? 'admin_dashboard' : 'security') ?>"><i class="bi bi-person me-2"></i>Account</a></li><li><a class="dropdown-item" href="?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li></ul></div></div></header><main class="app-main">
<?php else: ?><main class="container py-3">
<?php endif; ?>
<?php foreach ($_SESSION['flash'] ?? [] as $flash): ?><div class="alert alert-<?= layout_h($flash['type'] ?? 'info') ?> alert-dismissible fade show shadow-sm mb-3" role="alert"><?= layout_h($flash['message'] ?? '') ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endforeach; unset($_SESSION['flash']); ?>
<?php }

function foot(): void
{
    $account = user();
    echo $account ? '</main></div>' : '</main>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
