<?php
declare(strict_types=1);

/**
 * Account-facing routes. These run before the legacy page renderer so the
 * registration/profile flow is available without duplicating the dashboard.
 */
function account_escape(mixed $value): string {
    return htmlspecialchars($value === null ? '' : (string)$value, ENT_QUOTES, 'UTF-8');
}

function account_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        throw new RuntimeException('Please upload a credential file.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Credential files must be 5 MB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $extensions = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Upload a PDF, JPG, PNG, or WEBP file.');
    return ['tmp_name'=>(string)$file['tmp_name'], 'mime'=>$mime, 'extension'=>$extensions[$mime]];
}

function account_page(string $title, string $body): never {
    $safeTitle = account_escape($title);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safeTitle.' · CareNest</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#eff7f5;color:#173044}.panel{max-width:760px;margin:5rem auto}.brand{color:#12364a;font-weight:700;text-decoration:none;font-size:1.4rem}</style></head><body><main class="panel px-3"><a class="brand" href="?">+ CareNest</a>'.$body.'</main></body></html>';
    exit;
}

function account_register_page(): never {
    $doctor = ($_GET['role'] ?? '') === 'doctor';
    $fields = $doctor ? '<div class="alert alert-info">Doctor accounts are created as <strong>Verification Pending</strong> until an administrator reviews the submitted credentials.</div><div class="mb-3"><label class="form-label">Specialization</label><input class="form-control" name="specialization" required></div><div class="mb-3"><label class="form-label">Qualification</label><input class="form-control" name="qualification" required></div><div class="mb-3"><label class="form-label">Hospital / clinic</label><input class="form-control" name="clinic_name" required></div><div class="mb-3"><label class="form-label">License number</label><input class="form-control" name="license_number" required></div><div class="mb-3"><label class="form-label">Credential file</label><input class="form-control" type="file" name="credential" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>' : '<div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div><div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div><div class="mb-3"><label class="form-label">Nearest hospital</label><input class="form-control" name="nearest_hospital"></div>';
    $roleLinks = $doctor ? '<a href="?page=register">Register as patient</a>' : '<a href="?page=register&role=doctor">Register as doctor</a>';
    account_page('Create account', '<div class="card shadow-sm p-4 mt-4"><span class="text-uppercase small text-success fw-bold">'.($doctor?'Doctor registration':'Patient registration').'</span><h1 class="h3 mt-2">Create your account</h1><p class="text-muted">'.($doctor?'Submit your professional details for review.':'Create a simple private health account.').'</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="form" value="account_register"><input type="hidden" name="role" value="'.($doctor?'doctor':'patient').'"><div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>'.$fields.'<div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" required></div><button class="btn btn-primary w-100">Create '.($doctor?'doctor':'patient').' account</button></form><div class="d-flex justify-content-between mt-3 small"><a href="?page=login">Already have an account? Sign in</a>'.$roleLinks.'</div></div>');
}

function account_profile_page(): never {
    require_auth();
    $p = profile() ?? [];
    account_page('User Profile', '<div class="card shadow-sm p-4 mt-4"><div class="d-flex justify-content-between align-items-center"><div><span class="text-uppercase small text-success fw-bold">Account</span><h1 class="h3 mt-2">User Profile</h1></div><a class="btn btn-outline-secondary btn-sm" href="?action=logout">Sign out</a></div><form method="post" class="row g-3 mt-2"><input type="hidden" name="form" value="profile_update"><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="full_name" value="'.account_escape($p['full_name'] ?? '').'" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="'.account_escape($p['phone'] ?? '').'"></div><div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2">'.account_escape($p['address'] ?? '').'</textarea></div><div class="col-12"><label class="form-label">Nearest hospital</label><input class="form-control" name="nearest_hospital" value="'.account_escape($p['nearest_hospital'] ?? '').'"></div><div class="col-12"><button class="btn btn-primary">Save profile</button></div></form><hr class="my-4"><h2 class="h5">Change password</h2><form method="post" class="row g-3"><input type="hidden" name="form" value="profile_password"><div class="col-md-4"><input class="form-control" type="password" name="current_password" placeholder="Current password" required></div><div class="col-md-4"><input class="form-control" type="password" name="new_password" placeholder="New password" minlength="10" required></div><div class="col-md-4"><input class="form-control" type="password" name="confirm_password" placeholder="Confirm password" minlength="10" required></div><div class="col-12"><button class="btn btn-outline-primary">Change password</button></div></form></div>');
}

function account_route_dispatch(): void {
    $page = (string)($_GET['page'] ?? '');
    if ($page === 'register' && $_SERVER['REQUEST_METHOD'] !== 'POST') account_register_page();
    if ($page === 'security' && user()) redirect('?page=profile');
    if ($page === 'profile' && $_SERVER['REQUEST_METHOD'] !== 'POST') account_profile_page();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $form = (string)($_POST['form'] ?? '');
    if ($form === 'account_register') {
        try {
            $sb = new Supabase();
            $role = ($_POST['role'] ?? '') === 'doctor' ? 'doctor' : 'patient';
            if ($role === 'doctor') {
                register_doctor($sb, $_POST, $_FILES['credential'] ?? []);
                if (auth_token()) { $sb->db('PATCH','profiles','?id=eq.'.rawurlencode((string)user()['id']), ['role'=>'doctor','verification_status'=>'pending']); }
                account_page('Verification pending', '<div class="alert alert-success mt-4"><h1 class="h4">Registration submitted</h1><p class="mb-0">Your doctor account is <strong>Verification Pending</strong>. An administrator must review your credentials before clinician access is enabled.</p></div><a href="?page=login">Continue to sign in</a>');
            }
            sign_up(trim((string)$_POST['email']), (string)$_POST['password'], trim((string)$_POST['name']));
            if (auth_token()) $sb->db('PATCH','profiles','?id=eq.'.rawurlencode((string)user()['id']), ['phone'=>trim((string)($_POST['phone'] ?? '')), 'address'=>trim((string)($_POST['address'] ?? '')), 'nearest_hospital'=>trim((string)($_POST['nearest_hospital'] ?? ''))]);
            account_page('Account created', '<div class="alert alert-success mt-4">Patient account created successfully.</div><a href="?page=login">Continue to sign in</a>');
        } catch (Throwable $e) { account_page('Registration error', '<div class="alert alert-danger mt-4">'.account_escape(safe_error_message($e)).'</div><a href="?page=register">Go back</a>'); }
    }
    if ($form === 'profile_update') {
        require_auth(); update_user_profile(new Supabase(), (string)user()['id'], $_POST); flash('success','Your profile was updated.'); redirect('?page=profile');
    }
    if ($form === 'profile_password') {
        require_auth(); change_password((string)$_POST['current_password'], (string)$_POST['new_password'], (string)$_POST['confirm_password']); redirect('?page=login');
    }
}
