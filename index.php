<?php
declare(strict_types=1);
session_start();
ob_start();
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/layout.php';
require_once __DIR__ . '/src/doctor_portal.php';
require_once __DIR__ . '/src/medicine_portal.php';
require_once __DIR__ . '/src/admin_portal.php';

if (!configured()) { head('Setup required'); ?>
<div class="col-lg-7 mx-auto"><div class="card p-4"><h1 class="h3">Connect Smart Health Record</h1><p class="text-muted">Create <code>.env</code> from <code>.env.example</code>, then enter the Supabase project URL and anon/publishable key. The app will not use your database password.</p><p class="mb-0">Then run <code>database/schema.sql</code> in Supabase SQL Editor once.</p></div></div>
<?php foot(); exit; }

$requestedPage = (string)($_GET['page'] ?? '');
if (auth_token() && str_starts_with($requestedPage, 'doctor_')) { doctor_portal(); exit; }
if (auth_token() && str_starts_with($requestedPage, 'admin_')) { admin_portal(); exit; }

$action = $_GET['action'] ?? '';
try {
    if ($action === 'logout') { sign_out(); flash('success', 'You have been signed out.'); redirect('?page=login'); }
    if (in_array($action, ['view','download'], true)) {
        require_auth();
        $record = authorised_medical_record(new Supabase(), (string)($_GET['id'] ?? ''));
        if (empty($record['file_path'])) throw new RuntimeException('File not stored, please re-upload.');
        header('Location: ' . (new Supabase())->signedFileUrl((string)$record['file_path'], $action === 'download'));
        exit;
    }
    if ($action === 'credential_download') { require_admin(); $doctorId=(string)($_GET['doctor_id'] ?? ''); $rows=(new Supabase())->db('GET','profiles','?select=credential_path&id=eq.'.rawurlencode($doctorId).'&role=eq.doctor&limit=1'); if (empty($rows[0]['credential_path'])) throw new RuntimeException('Credential document is not available.'); header('Location: '.(new Supabase())->signedFileUrl((string)$rows[0]['credential_path'])); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sb = new Supabase(); $form = $_POST['form'] ?? '';
        if ($form === 'login') {
            $requestedPortal=(string)($_GET['role'] ?? '');
            $portal = $_POST['portal'] ?? (in_array($requestedPortal,['doctor','admin'],true) ? $requestedPortal : 'patient');
            sign_in(trim($_POST['email']), $_POST['password']);
            $role = current_role();
            if ($role !== $portal) { sign_out(); throw new RuntimeException($portal === 'doctor' ? 'This is not a clinician account. Please use the correct sign-in.' : ($portal === 'admin' ? 'This is not an administrator account.' : 'This is not a patient account. Please use the correct sign-in.')); }
            flash('success', 'Welcome back.'); redirect('?page=' . ($role === 'admin' ? 'admin_dashboard' : ($role === 'doctor' ? 'doctor_dashboard' : 'dashboard')));
        }
        if ($form === 'register') { sign_up(trim($_POST['email']), $_POST['password'], trim($_POST['name'])); if (auth_token()) { flash('success', 'Welcome to CareNest — your private health space is ready.'); redirect('?page=dashboard'); } flash('success', 'Account created. Please sign in to continue.'); redirect('?page=login'); }
        require_auth(); $uid = user()['id'];
        if ($form === 'password_change') { change_password((string)($_POST['current_password'] ?? ''),(string)($_POST['new_password'] ?? ''),(string)($_POST['confirm_password'] ?? '')); flash('success','Password changed. Please sign in again.'); redirect('?page=login'); }
        if ($form === 'prescription_scan') validate_prescription_upload($_FILES['prescription'] ?? []);
        if ($form === 'metric') { $sb->db('POST','health_metrics','', [['patient_id'=>$uid,'metric_type'=>trim($_POST['metric_type']),'value'=>(float)$_POST['value'],'unit'=>trim($_POST['unit']),'measured_at'=>date('c'),'notes'=>trim($_POST['notes'])]]); flash('success','Health metric saved.'); }
        if ($form === 'medicine') { $sb->db('POST','medicines','', [array_merge(['patient_id'=>$uid], medicine_fields($_POST))]); flash('success','Medicine added.'); }
        if ($form === 'medicine_create') { $sb->db('POST','medicines','', [array_merge(['patient_id'=>$uid], medicine_fields($_POST))]); flash('success','Medicine added to your plan.'); }
        if ($form === 'medicine_update') {
    $id=(string)($_POST['id'] ?? '');
    if (!ctype_digit($id)) {
        throw new RuntimeException('Invalid medicine selected.');
    }
    $sb->db('PATCH','medicines','?id=eq.'.rawurlencode($id).'&patient_id=eq.'.rawurlencode($uid), medicine_fields($_POST));
    flash('success','Medicine updated.');
}

if ($form === 'medicine_delete') {
    $id=(string)($_POST['id'] ?? '');
    if (!ctype_digit($id) || ($_POST['confirm_delete'] ?? '') !== '1') {
        throw new RuntimeException('Invalid medicine selected.');
    }
    $sb->db('DELETE','medicines','?id=eq.'.rawurlencode($id).'&patient_id=eq.'.rawurlencode($uid));
    flash('success','Medicine removed from your plan.');
}        if ($form === 'appointment') { $sb->db('POST','appointments','', [['patient_id'=>$uid,'scheduled_at'=>date('c',strtotime($_POST['scheduled_at'])),'reason'=>trim($_POST['reason'])]]); flash('success','Appointment saved.'); }
        if ($form === 'appointment_request') { $doctorId=(string)($_POST['doctor_id'] ?? ''); $when=(string)($_POST['preferred_at'] ?? ''); if (!preg_match('/^[0-9a-f-]{36}$/i',$doctorId) || strtotime($when) === false) throw new RuntimeException('Choose a doctor and a valid preferred date/time.'); $sb->rpc('carenest_request_appointment',['target_doctor'=>$doctorId,'preferred_at'=>date('c',strtotime($when)),'request_reason'=>trim((string)($_POST['reason'] ?? ''))]); flash('success','Appointment request sent to the doctor.'); }
        if ($form === 'record') { store_medical_record($sb, $uid, $_FILES['file'] ?? [], trim((string)($_POST['title'] ?? '')), 'medical_report'); flash('success','Medical record uploaded securely.'); }
        if ($form === 'prescription_scan') { $stored=store_medical_record($sb, $uid, $_FILES['prescription'] ?? [], 'Prescription · '.date('d M Y'), 'prescription'); $items=extract_prescription((string)file_get_contents($stored['tmp_name']),$stored['mime']); foreach($items as $item) $sb->db('POST','medicines','', [['patient_id'=>$uid,'medicine_name'=>$item['name'],'name'=>$item['name'],'dosage'=>$item['dosage'],'frequency'=>$item['frequency'],'notes'=>'Extracted from prescription — please verify with your clinician.']]); flash('success','Prescription saved securely and '.count($items).' medicine(s) extracted. Please check every name and dose before relying on it.'); }
        if ($form === 'share') { $doctors = $sb->db('GET','profiles','?select=id,role&email=eq.' . rawurlencode(trim($_POST['doctor_email']))); if (!$doctors || $doctors[0]['role'] !== 'doctor') throw new RuntimeException('No authorised doctor profile was found for that email.'); $sb->db('POST','record_sharing','?on_conflict=patient_id,doctor_id', [['patient_id'=>$uid,'doctor_id'=>$doctors[0]['id'],'expires_at'=>$_POST['expires_at'] ? date('c',strtotime($_POST['expires_at'])) : null]]); flash('success','Access shared with the doctor.'); }
        if ($form === 'report') { $metrics = $sb->db('GET','health_metrics','?select=metric_type,value,unit,measured_at&patient_id=eq.' . $uid . '&order=measured_at.desc&limit=20'); $text = report_text($metrics); $sb->db('POST','ai_reports','', [['patient_id'=>$uid,'report_text'=>$text]]); flash('success','A new health summary was saved.'); }
        redirect('?page=' . urlencode($_POST['return_page'] ?? 'dashboard'));
    }
} catch (Throwable $e) { flash('danger', safe_error_message($e)); redirect('?page=' . urlencode($_POST['return_page'] ?? 'login')); }

function gemini_generate(array $parts): string {
    $key = env('GEMINI_API_KEY');
    if ($key === '') {
        throw new RuntimeException('Gemini AI is not configured. Add GEMINI_API_KEY to .env.');
    }

    $model = env('GEMINI_MODEL', 'gemini-2.0-flash');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => $parts,
        ]],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 800,
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 45,
    ]);

    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $json = json_decode($raw ?: '', true);
    if ($raw === false || $code >= 400) {
        throw new RuntimeException(
            'Gemini AI request failed: ' . ($json['error']['message'] ?? $error ?: 'Unknown error')
        );
    }

    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($text) || trim($text) === '') {
        throw new RuntimeException('Gemini AI did not return a usable response.');
    }

    return trim($text);
}

function report_text(array $metrics): string {
    $facts = array_map(
        fn($m) => $m['metric_type'] . ': ' . $m['value'] . ' ' . $m['unit']
            . ' (' . date('d M', strtotime($m['measured_at'])) . ')',
        $metrics
    );

    $fallback = "• Your latest measurements are recorded and ready to review.\n"
        . "• Recent values: " . ($facts ? implode('; ', $facts) : 'no measurements yet') . ".\n"
        . "• This is informational only, not medical advice. Speak with a clinician if you are concerned.";

    if (!$metrics || env('GEMINI_API_KEY') === '') {
        return $fallback;
    }

    try {
        return gemini_generate([[
            'text' => "Return exactly three short bullet points in plain language about these health measurements. "
                . "Each line must start with •. Do not diagnose, do not use difficult medical words, and end with a "
                . "clear statement that this is not medical advice.\n\nMeasurements:\n" . implode("\n", $facts),
        ]]);
    } catch (Throwable $e) {
        error_log('[CareNest Gemini report] ' . $e->getMessage());
        return $fallback;
    }
}

function extract_prescription(string $image, string $mime): array {
    if (env('GEMINI_API_KEY') === '') {
        throw new RuntimeException('AI prescription scanning is not configured yet.');
    }

    $prompt = 'Read this prescription image. Return only valid JSON with no markdown. '
        . 'Return an array of objects, each with string fields: name, dosage, frequency. '
        . 'Extract only text clearly visible. If uncertain, use "Needs verification". '
        . 'This is transcription only, not medical advice.';

    $text = gemini_generate([
        ['text' => $prompt],
        ['inline_data' => [
           'mime_type' => $mime,
           'data' => base64_encode($image),
        ]],
    ]);

    $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
    $items = json_decode($text, true);

    if (!is_array($items)) {
        throw new RuntimeException(
            'The prescription could not be read clearly. Try a brighter photo or enter medicines manually.'
        );
    }

    return array_values(array_filter(
        $items,
        fn($item) => is_array($item) && !empty($item['name'])
    ));
}

function validate_prescription_upload(array $file): void {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        throw new RuntimeException('Choose a clear prescription image to scan.');
    }

    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Prescription images must be between 1 byte and 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('For prescription scanning, upload a JPG, PNG or WEBP image.');
    }
}

function validated_medical_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        throw new RuntimeException('Choose a medical file to upload.');
    }

    if (($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Medical files must be 5 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Upload a PDF, JPG, PNG or WEBP file.');
    }

    $originalName = preg_replace(
        '/[^A-Za-z0-9._ -]/',
        '_',
        basename((string) ($file['name'] ?? 'medical-file'))
    );

    $givenExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
    ];

    if (!in_array($givenExtension, $allowedExtensions[$mime], true)) {
        throw new RuntimeException('The file extension does not match its actual file type.');
    }

    return [
        'tmp_name' => (string) $file['tmp_name'],
        'mime' => $mime,
        'extension' => $extensions[$mime],
        'display_name' => $originalName,
        'size' => (int) $file['size'],
    ];
}

function store_medical_record(
    Supabase $sb,
    string $patientId,
    array $file,
    string $title,
    string $recordType
): array {
    $upload = validated_medical_upload($file);

    if ($recordType === 'prescription' && !str_starts_with($upload['mime'], 'image/')) {
        throw new RuntimeException('Prescription scanning requires a JPG, PNG or WEBP image.');
    }

    $path = $patientId . '/' . $recordType . '/' . bin2hex(random_bytes(24)) . '.' . $upload['extension'];

    $sb->storageUpload(
        $path,
        (string) file_get_contents($upload['tmp_name']),
        $upload['mime']
    );

    $safeTitle = $title !== '' ? $title : $upload['display_name'];

    $sb->db('POST', 'medical_records', '', [[
        'patient_id' => $patientId,
        'owner_id' => $patientId,
        'title' => $safeTitle,
        'record_title' => $safeTitle,
        'record_type' => $recordType,
        'file_path' => $path,
        'file_type' => $upload['mime'],
        'mime_type' => $upload['mime'],
        'file_size' => $upload['size'],
        'original_file_name' => $upload['display_name'],
        'processing_status' => 'stored',
    ]]);

    return $upload;
}

function authorised_medical_record(Supabase $sb, string $id): array {
    if (!ctype_digit($id)) {
        throw new RuntimeException('Invalid medical record selected.');
    }

    $rows = $sb->db(
        'GET',
        'medical_records',
        '?select=*&id=eq.' . rawurlencode($id) . '&limit=1'
    );

    $record = $rows[0] ?? null;
    if (!$record) {
        throw new RuntimeException('Record not found or access is not permitted.');
    }

    $userId = (string) user()['id'];
    $role = current_role();
    $patientId = (string) ($record['patient_id'] ?? '');

    $allowed = $role === 'patient' && hash_equals($patientId, $userId);

    if (!$allowed && $role === 'doctor') {
        $allowed = doctor_patient_access($sb, $userId, $patientId);
    }

    if (!$allowed) {
        throw new RuntimeException('Record access is not permitted.');
    }

    return $record;
}

$page = $_GET['page'] ?? (user() ? 'dashboard' : 'login');
if (!user() && !in_array($page,['login','register'],true)) $page='login';
if (user() && in_array($page,['login','register'],true)) {
    redirect('?page=' . (current_role() === 'doctor' ? 'doctor_dashboard' : 'dashboard'));
}
if ($page === 'login' || $page === 'register') { head($page==='login'?'Sign in':'Create account'); $doctor = ($_GET['role'] ?? '') === 'doctor'; ?>
</main><div class="auth-page"><div class="auth-wrap row g-0 shadow-lg"><section class="col-md-6 auth-hero"><div class="eyebrow text-white-50 mb-3">CareNest · health, held with care</div><h1>Your story deserves a safer place.</h1><p class="lead mt-4 text-white-50">A calm, private home for your records, medicines, appointments and meaningful health insights.</p><div class="mini-feature"><strong>✦ Intelligent summaries</strong><br><span class="small text-white-50">Make your health data easier to understand.</span></div><div class="mini-feature"><strong>⌁ Clinician-ready sharing</strong><br><span class="small text-white-50">You decide when and who can see your records.</span></div></section><section class="col-md-6 auth-panel"><a class="brand text-decoration-none" href="?"><span class="brand-mark">+</span>CareNest</a><div class="mt-5"><span class="eyebrow"><?= $doctor ? 'Clinician portal' : ($page==='login'?'Welcome back':'Patient registration') ?></span><h2 class="mt-2" style="font-family:'Playfair Display',serif;font-weight:700"><?= $page==='login' ? ($doctor?'Doctor sign in':'Sign in to your space') : 'Create your health space' ?></h2><p class="text-muted mb-4"><?= $doctor ? 'Verified clinicians can securely review shared patient records.' : 'Your records stay private and under your control.' ?></p><form method="post"><input type="hidden" name="form" value="<?= $page==='login'?'login':'register' ?>"><?php if($page==='register'): ?><div class="mb-3"><label class="form-label fw-semibold">Full name</label><input class="form-control" name="name" placeholder="e.g. Aarav Sharma" required></div><?php endif ?><div class="mb-3"><label class="form-label fw-semibold">Email address</label><input type="email" class="form-control" name="email" placeholder="you@example.com" required></div><div class="mb-4"><label class="form-label fw-semibold">Password</label><input type="password" class="form-control" name="password" minlength="8" placeholder="Minimum 8 characters" required></div><button class="btn btn-primary w-100"><?= $page==='login'?'Continue securely':'Create patient account' ?></button></form><?php if($doctor): ?><p class="small text-muted mt-3">Doctor accounts require verification by the system administrator.</p><?php endif ?><div class="d-flex justify-content-between small mt-4"><a href="?page=<?= $page==='login'?'register':'login' ?>" class="text-decoration-none"><?= $page==='login'?'Create a patient account':'Already have an account? Sign in' ?></a><?php if(!$doctor): ?><a href="?page=login&role=doctor" class="text-decoration-none">Doctor login →</a><?php else: ?><a href="?page=login" class="text-decoration-none">Patient login →</a><?php endif ?></div></div></section></div></div><?php foot(); exit; }

require_auth(); $sb=new Supabase(); $uid=user()['id'];
if (current_role() === 'doctor') { redirect('?page=doctor_dashboard'); }
if ($page === 'medicine_manage') { medicine_portal($sb, (string)$uid); exit; }
$tabs=['dashboard'=>'⌂  Overview','metrics'=>'⌁  Vitals & trends','records'=>'▣  Medical records','medicine_manage'=>'◌  My medicines','appointments'=>'◷  Appointments','notifications'=>'◉  Notifications','sharing'=>'♧  Share with doctor','reports'=>'✦  AI health insights','security'=>'⌾  Security'];
head($tabs[$page] ?? 'Dashboard'); ?>
<div class="row g-4"><aside class="col-lg-3"><div class="card p-3 side-nav"><div class="p-2 mb-2 border-bottom"><div class="small text-muted">YOUR HEALTH SPACE</div><strong class="d-block mt-1">Private & protected</strong></div><div class="nav nav-pills flex-lg-column gap-1"><?php foreach($tabs as $key=>$label): ?><a class="nav-link <?= $page===$key?'active':'' ?>" href="?page=<?=$key?>"><?= $label ?></a><?php endforeach ?></div><div class="mt-3 p-3 rounded-4" style="background:#e8f7f3"><strong class="small">Need quick help?</strong><p class="small text-muted mb-2">Your AI summary is ready whenever your records are.</p><a href="?page=reports" class="small fw-bold">Open insights →</a></div></div></aside><section class="col-lg-9"><?php
if ($page==='dashboard') { $metrics=$sb->db('GET','health_metrics','?select=*&patient_id=eq.'.$uid.'&order=measured_at.desc&limit=5'); $meds=$sb->db('GET','medicines','?select=*&patient_id=eq.'.$uid.'&order=created_at.desc&limit=3'); $appts=$sb->db('GET','appointments','?select=*&patient_id=eq.'.$uid.'&order=scheduled_at.asc&limit=3'); ?>
<div class="glass-card p-4 p-md-5 mb-4"><span class="eyebrow">Your personal health companion</span><h1 class="display-title mb-2">Good to see you again.</h1><p class="text-muted mb-0">Everything important, gently organised in one place.</p></div><div class="row g-3 mb-4"><div class="col-md-4"><div class="card p-4"><div class="text-muted small">Measurements tracked</div><div class="metric"><?=count($metrics)?></div><span class="small text-success">● Ready for your next update</span></div></div><div class="col-md-4"><div class="card p-4"><div class="text-muted small">Medicines on your list</div><div class="metric"><?=count($meds)?></div><span class="small text-muted">Keep your routine visible</span></div></div><div class="col-md-4"><div class="card p-4"><div class="text-muted small">Appointments ahead</div><div class="metric"><?=count($appts)?></div><span class="small text-muted">Your care, planned ahead</span></div></div></div><div class="row g-4"><div class="col-lg-7"><div class="card p-4 h-100"><div class="d-flex justify-content-between"><h2 class="h5">Recent health activity</h2><a href="?page=metrics" class="small">View all</a></div><?php if(!$metrics): ?><p class="text-muted mb-0">Start by recording a vital or uploading a report.</p><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><tbody><?php foreach($metrics as $m): ?><tr><td><span class="role-chip"><?=htmlspecialchars($m['metric_type'])?></span></td><td><strong><?=$m['value']?> <?=htmlspecialchars($m['unit'])?></strong></td><td class="text-muted small"><?=date('d M Y',strtotime($m['measured_at']))?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?></div></div><div class="col-lg-5"><div class="card p-4 h-100" style="background:linear-gradient(145deg,#e5f9f4,#fff)!important"><span class="eyebrow">CareNest AI</span><h2 class="h4 mt-2">See the pattern, not just the numbers.</h2><p class="text-muted small">Generate a plain-language summary from your recorded health metrics.</p><a class="btn btn-primary" href="?page=reports">Open AI insights</a></div></div></div><?php }
elseif ($page==='metrics') { $items=$sb->db('GET','health_metrics','?select=*&patient_id=eq.'.$uid.'&order=measured_at.desc'); ?>
<h1 class="h3">Health metrics</h1><div class="card p-4 mb-4"><form method="post" class="row g-3"><input type="hidden" name="form" value="metric"><input type="hidden" name="return_page" value="metrics"><div class="col-md-4"><input class="form-control" name="metric_type" placeholder="e.g. Blood pressure" required></div><div class="col-md-3"><input class="form-control" name="value" type="number" step="any" placeholder="Value" required></div><div class="col-md-3"><input class="form-control" name="unit" placeholder="Unit, e.g. mmHg" required></div><div class="col-12"><input class="form-control" name="notes" placeholder="Optional notes"></div><div class="col-12"><button class="btn btn-primary">Add measurement</button></div></form></div><?php foreach($items as $i): ?><div class="card p-3 mb-2"><strong><?=htmlspecialchars($i['metric_type'])?>: <?=$i['value']?> <?=htmlspecialchars($i['unit'])?></strong><span class="small text-muted ms-2"><?=date('d M Y H:i',strtotime($i['measured_at']))?></span></div><?php endforeach; }
elseif ($page==='records') { $items=$sb->db('GET','medical_records','?select=*&patient_id=eq.'.$uid.'&order=created_at.desc'); ?>
<div class="d-flex align-items-end justify-content-between mb-4"><div><span class="eyebrow">Your secure archive</span><h1 class="display-title mb-0">Medical records</h1></div><span class="role-chip">Private uploads</span></div><div class="row g-4 mb-4"><div class="col-lg-7"><div class="card p-4 h-100"><h2 class="h5">Upload a report or prescription</h2><p class="small text-muted">PDF, JPG, PNG or WEBP · maximum 5 MB.</p><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="form" value="record"><input type="hidden" name="return_page" value="records"><div class="col-md-5"><input class="form-control" name="title" placeholder="e.g. Blood test · Sept 2026"></div><div class="col-md-5"><input class="form-control" type="file" name="file" accept="application/pdf,image/jpeg,image/png,image/webp" required></div><div class="col-md-2"><button class="btn btn-primary w-100">Upload</button></div></form></div></div><div class="col-lg-5"><div class="card p-4 h-100" style="background:#eaf8f5!important"><span class="eyebrow">Prescription assistant</span><h2 class="h5 mt-2">Save and scan a prescription</h2><p class="small text-muted">The image is securely saved first, then medicines are extracted for review.</p><form method="post" enctype="multipart/form-data" class="d-flex gap-2"><input type="hidden" name="form" value="prescription_scan"><input type="hidden" name="return_page" value="records"><input class="form-control form-control-sm" type="file" name="prescription" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-primary btn-sm">Save & scan</button></form></div></div></div><div class="d-flex align-items-center justify-content-between mb-3"><h2 class="h5 mb-0">Saved documents</h2><span class="small text-muted"><?=count($items)?> record(s)</span></div><?php foreach($items as $i): $path=trim((string)($i['file_path'] ?? '')); $mime=(string)($i['mime_type'] ?? $i['file_type'] ?? ''); $image=str_starts_with($mime,'image/'); ?><div class="card p-3 mb-2 d-flex justify-content-between align-items-center gap-3"><?php if($path && $image): ?><img src="?action=view&amp;id=<?=urlencode((string)$i['id'])?>" alt="Document thumbnail" style="width:64px;height:64px;object-fit:cover;border-radius:10px"><?php elseif($path): ?><div class="fs-2">📄</div><?php else: ?><div class="fs-2">⚠️</div><?php endif; ?><span class="flex-grow-1"><strong><?=htmlspecialchars($i['original_file_name'] ?? $i['title'] ?? $i['record_title'] ?? 'Medical record')?></strong><span class="small text-muted ms-2"><?=date('d M Y',strtotime($i['created_at']))?></span><div class="small text-muted"><?=htmlspecialchars($mime ?: 'Unknown file type')?><?=!empty($i['file_size']) ? ' · '.number_format((int)$i['file_size']/1024,1).' KB' : ''?></div><?php if(!$path): ?><div class="small text-danger">File not stored, please re-upload.</div><?php endif; ?></span><?php if($path): ?><span class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" target="_blank" href="?action=view&amp;id=<?=urlencode((string)$i['id'])?>">Open</a><a class="btn btn-sm btn-outline-secondary" href="?action=download&amp;id=<?=urlencode((string)$i['id'])?>">Download</a></span><?php endif; ?></div><?php endforeach; }
elseif ($page==='medicines') { $items=$sb->db('GET','medicines','?select=*&patient_id=eq.'.$uid.'&order=created_at.desc'); ?>
<div class="d-flex align-items-end justify-content-between mb-4"><div><span class="eyebrow">Gentle reminders</span><h1 class="display-title mb-0">My medicines</h1></div><button class="btn btn-outline-primary btn-sm" id="enable-reminders">Enable reminders</button></div><div class="card p-4 mb-4"><form method="post" class="row g-3"><input type="hidden" name="form" value="medicine"><input type="hidden" name="return_page" value="medicines"><div class="col-md-4"><label class="small fw-bold mb-1">Medicine</label><input class="form-control" name="name" placeholder="Medicine name" required></div><div class="col-md-3"><label class="small fw-bold mb-1">Dose</label><input class="form-control" name="dosage" placeholder="e.g. 500 mg"></div><div class="col-md-3"><label class="small fw-bold mb-1">How often?</label><input class="form-control" name="frequency" placeholder="e.g. Twice daily"></div><div class="col-md-2"><label class="small fw-bold mb-1">Reminder</label><input class="form-control" name="reminder_time" type="time"></div><div class="col-md-4"><input class="form-control" name="start_date" type="date"></div><div class="col-md-8"><input class="form-control" name="notes" placeholder="Optional instructions"></div><div><button class="btn btn-primary">Add to my routine</button></div></form></div><div class="row g-3"><?php foreach($items as $i): ?><div class="col-md-6"><div class="card p-4 medicine-card" data-name="<?=htmlspecialchars($i['name'])?>" data-dose="<?=htmlspecialchars($i['dosage'] ?? '')?>" data-time="<?=htmlspecialchars($i['reminder_time'] ?? '')?>"><div class="d-flex justify-content-between"><strong><?=htmlspecialchars($i['name'])?></strong><?php if(!empty($i['reminder_time'])):?><span class="role-chip">⌚ <?=date('g:i A',strtotime($i['reminder_time']))?></span><?php endif?></div><div class="text-muted mt-2"><?=htmlspecialchars($i['dosage'] ?? 'Dose not entered')?> · <?=htmlspecialchars($i['frequency'] ?? 'Schedule not entered')?></div><div class="small text-muted mt-2"><?=htmlspecialchars($i['notes'] ?? '')?></div></div></div><?php endforeach; ?></div><script>document.getElementById('enable-reminders')?.addEventListener('click',async()=>{if('Notification'in window){await Notification.requestPermission();alert('Reminders are enabled while CareNest is open. Keep this page open near the medicine time.')}});setInterval(()=>{if(Notification?.permission!=='granted')return;const now=new Date(),t=now.toTimeString().slice(0,5);document.querySelectorAll('.medicine-card').forEach(x=>{if(x.dataset.time===t){const k=x.dataset.name+t+now.toDateString();if(!localStorage.getItem(k)){new Notification('CareNest medicine reminder',{body:`Time to take ${x.dataset.name} ${x.dataset.dose}`});localStorage.setItem(k,'1')}}})},30000)</script><?php }
elseif ($page==='appointments') { $items=$sb->db('GET','appointments','?select=*&patient_id=eq.'.$uid.'&order=scheduled_at.asc'); $doctors=$sb->db('GET','profiles','?select=id,full_name,email,specialization,clinic_name,qualification&role=eq.doctor&verification_status=eq.verified&order=full_name.asc'); ?>
<div class="page-heading"><h1>Appointments</h1><p>Request a time with a verified clinician. Your request is sent directly to their workspace.</p></div><div class="card p-4 mb-4"><h2 class="h5 mb-3">Available doctors</h2><?php if(!$doctors): ?><div class="empty-state"><i class="bi bi-person-check"></i>No verified doctors are available yet.</div><?php else: ?><div class="row g-3"><?php foreach($doctors as $doctor): ?><div class="col-md-6"><div class="border rounded-4 p-3 h-100"><div class="d-flex justify-content-between"><div><strong><?=h($doctor['full_name'])?></strong><div class="small text-muted"><?=h($doctor['specialization'] ?? 'Clinician')?> · <?=h($doctor['clinic_name'] ?? '')?></div><div class="small text-muted mt-1"><?=h($doctor['qualification'] ?? '')?></div></div><span class="role-chip">✓ Verified</span></div><form method="post" class="row g-2 mt-2"><input type="hidden" name="form" value="appointment_request"><input type="hidden" name="return_page" value="appointments"><input type="hidden" name="doctor_id" value="<?=h($doctor['id'])?>"><div class="col-md-6"><input class="form-control form-control-sm" type="datetime-local" name="preferred_at" required></div><div class="col-md-6"><input class="form-control form-control-sm" name="reason" placeholder="Reason (optional)"></div><div><button class="btn btn-sm btn-primary">Request appointment</button></div></form></div></div><?php endforeach ?></div><?php endif ?></div><h2 class="h5 mb-3">My appointment requests</h2><?php if(!$items): ?><div class="empty-state card"><i class="bi bi-calendar2-week"></i>No appointment requests yet.</div><?php endif; foreach($items as $i): ?><div class="card p-3 mb-2"><div class="d-flex justify-content-between"><strong><?=date('d M Y, H:i',strtotime($i['scheduled_at']))?></strong><span class="role-chip"><?=h($i['status'] ?? 'pending')?></span></div><div class="small text-muted mt-1"><?=h($i['reason'] ?? 'No reason provided')?><?=!empty($i['response_note'])?' · '.h($i['response_note']):''?></div></div><?php endforeach; }
elseif ($page==='notifications') { $items=$sb->db('GET','notifications','?select=*&user_id=eq.'.rawurlencode((string)$uid).'&order=created_at.desc&limit=50'); ?><div class="page-heading"><h1>Notifications</h1><p>Appointment updates and other CareNest activity appear here.</p></div><?php if(!$items): ?><div class="empty-state card"><i class="bi bi-bell"></i>You are all caught up.</div><?php endif; foreach($items as $n): ?><div class="card p-3 mb-2 <?=empty($n['read_at'])?'border-primary':''?>"><strong><?=h($n['title'])?></strong><div class="mt-1 text-muted"><?=h($n['body'])?></div><div class="small text-muted mt-2"><?=h($n['created_at'])?></div></div><?php endforeach; }
elseif ($page==='sharing') { $items=$sb->db('GET','record_sharing','?select=*&patient_id=eq.'.$uid.'&order=created_at.desc'); ?>
<div class="glass-card p-4 p-md-5 mb-4"><span class="eyebrow">You stay in control</span><h1 class="display-title">Share with a doctor</h1><p class="text-muted mb-0">Give a verified clinician secure, time-limited access to your health information. You can remove access later.</p></div><div class="card p-4 mb-4"><div class="d-flex gap-3 mb-3"><span class="brand-mark">♧</span><div><strong>Invite a verified doctor</strong><p class="small text-muted mb-0">The doctor must already have an account and be verified as a clinician.</p></div></div><form method="post" class="row g-3"><input type="hidden" name="form" value="share"><input type="hidden" name="return_page" value="sharing"><div class="col-md-7"><label class="small fw-bold mb-1">Doctor's email</label><input class="form-control" type="email" name="doctor_email" placeholder="doctor@clinic.example" required></div><div class="col-md-3"><label class="small fw-bold mb-1">Access expires (optional)</label><input class="form-control" type="date" name="expires_at"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Share safely</button></div></form></div><h2 class="h5 mb-3">People with access</h2><?php if(!$items): ?><div class="card p-4 text-muted">No records are currently shared. You are in full control.</div><?php endif; foreach($items as $i): ?><div class="card p-3 mb-2 d-flex justify-content-between"><span><strong>Verified doctor</strong><span class="role-chip ms-2">Active</span></span><span class="small text-muted"><?= $i['expires_at'] ? 'Until '.date('d M Y',strtotime($i['expires_at'])) : 'No expiry set' ?></span></div><?php endforeach; }
elseif ($page==='security') { ?>
<div class="d-flex align-items-end justify-content-between mb-4"><div><span class="eyebrow">Account protection</span><h1 class="display-title mb-0">Security</h1></div><span class="role-chip">Signed in securely</span></div><div class="row g-4"><div class="col-lg-7"><div class="card p-4"><h2 class="h5">Change password</h2><p class="small text-muted">For your protection, confirm your current password before choosing a new one.</p><form method="post" class="row g-3"><input type="hidden" name="form" value="password_change"><div class="col-12"><label class="form-label small fw-bold">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password" required></div><div class="col-md-6"><label class="form-label small fw-bold">New password</label><input class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="10" required></div><div class="col-md-6"><label class="form-label small fw-bold">Confirm new password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password" minlength="10" required></div><div class="col-12"><button class="btn btn-primary">Update password</button></div></form></div></div><div class="col-lg-5"><div class="card p-4 h-100"><h2 class="h5">Session controls</h2><p class="small text-muted">Signing out clears this browser session. Protected pages also verify your authenticated role on the server.</p><a class="btn btn-outline-primary" href="?action=logout">Sign out from this device</a></div></div></div>
<?php } else { $items=$sb->db('GET','ai_reports','?select=*&patient_id=eq.'.$uid.'&order=created_at.desc'); ?>
<div class="glass-card p-4 p-md-5 mb-4"><span class="eyebrow">CareNest AI</span><h1 class="display-title">Health insights, made simple.</h1><p class="text-muted mb-4">We turn the measurements you save into short, understandable points. This is not medical advice.</p><form method="post"><input type="hidden" name="form" value="report"><input type="hidden" name="return_page" value="reports"><button class="btn btn-primary">✦ Generate new insights</button></form></div><?php foreach($items as $i): $raw=trim($i['report_text']); $points=str_contains($raw,"\n") ? preg_split('/\R/', $raw) : preg_split('/(?<=\.)\s+(?=[A-Z])/', $raw); ?><article class="card p-4 mb-3"><div class="d-flex justify-content-between align-items-center mb-3"><strong>Insight summary</strong><span class="role-chip"><?=date('d M Y',strtotime($i['created_at']))?></span></div><div class="insight-points"><?php foreach($points as $point): if(trim($point)!==''): ?><div class="d-flex gap-2 mb-3"><span class="text-success fw-bold">✦</span><span><?=htmlspecialchars(ltrim(trim($point), "•- "))?></span></div><?php endif; endforeach ?></div></article><?php endforeach; }
?></section></div><?php foot();
