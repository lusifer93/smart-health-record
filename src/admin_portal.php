<?php
declare(strict_types=1);

function require_admin(): void { require_role('admin'); }

function admin_status_class(string $status): string {
    return match ($status) {
        'verified' => 'status-verified',
        'rejected' => 'status-rejected',
        'under_review' => 'status-review',
        default => 'status-pending',
    };
}

function admin_portal(): void {
    require_admin();
    $sb = new Supabase();
    $page = (string)($_GET['page'] ?? 'admin_dashboard');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'review_doctor') {
        try {
            $doctorId = (string)($_POST['doctor_id'] ?? '');
            $status = (string)($_POST['verification_status'] ?? '');
            if (!preg_match('/^[0-9a-f-]{36}$/i', $doctorId)) {
                throw new RuntimeException('Invalid doctor selected.');
            }
            if (!in_array($status, ['pending', 'under_review', 'verified', 'rejected'], true)) {
                throw new RuntimeException('Invalid verification status.');
            }
            $sb->rpc('carenest_set_doctor_verification', [
                'target_doctor' => $doctorId,
                'next_status' => $status,
                'review_reason' => trim((string)($_POST['verification_reason'] ?? '')),
            ]);
            flash('success', 'Doctor verification status updated.');
        } catch (Throwable $e) {
            flash('danger', safe_error_message($e));
        }
        redirect('?page=admin_verification');
    }

    $tabs = ['admin_dashboard' => 'Overview', 'admin_verification' => 'Doctor verification'];
    if (!isset($tabs[$page])) $page = 'admin_dashboard';

    $doctors = $sb->db(
        'GET',
        'profiles',
        '?select=id,full_name,email,phone,address,specialization,specialization_other,clinic_name,qualification,license_number,credential_path,credential_document_type,credential_number,issuing_institution,verification_status,verification_reason,verification_updated_at&role=eq.doctor&order=created_at.desc'
    );

    head('Administrator · ' . $tabs[$page]); ?>
<style>
.admin-shell{max-width:1180px;margin:auto}.admin-nav{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}.admin-nav a{padding:.55rem .85rem;border-radius:10px;background:#fff;color:#173044;text-decoration:none;border:1px solid #d8e8e4}.admin-nav a.active{background:#173044;color:#fff}.doctor-review-card{border:1px solid #d8e8e4;border-radius:16px;background:#fff;padding:1.25rem;margin-bottom:1rem}.doctor-detail{background:#f5faf8;border-radius:12px;padding:1rem}.doctor-detail dt{font-size:.72rem;text-transform:uppercase;color:#6b7c85;font-weight:700}.doctor-detail dd{margin-bottom:.75rem}.status-verified{color:#147a50;font-weight:700}.status-pending{color:#9a6400;font-weight:700}.status-review{color:#1769aa;font-weight:700}.status-rejected{color:#b42318;font-weight:700}
</style>
<div class="admin-shell">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><span class="eyebrow">Restricted area</span><h1 class="h3 mb-0">CareNest administration</h1></div>
    <a class="btn btn-sm btn-outline-primary" href="?action=logout">Sign out</a>
  </div>
  <nav class="admin-nav"><?php foreach ($tabs as $key => $label): ?><a class="<?=$page === $key ? 'active' : ''?>" href="?page=<?=$key?>"><?=h($label)?></a><?php endforeach ?></nav>

<?php if ($page === 'admin_dashboard'): ?>
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card p-4"><div class="small text-muted">DOCTOR PROFILES</div><div class="metric"><?=count($doctors)?></div></div></div>
    <div class="col-md-4"><div class="card p-4"><div class="small text-muted">PENDING REVIEWS</div><div class="metric"><?=count(array_filter($doctors, fn(array $d): bool => in_array(($d['verification_status'] ?? 'pending'), ['pending', 'under_review'], true)))?></div></div></div>
    <div class="col-md-4"><div class="card p-4"><div class="small text-muted">VERIFIED DOCTORS</div><div class="metric"><?=count(array_filter($doctors, fn(array $d): bool => ($d['verification_status'] ?? '') === 'verified'))?></div></div></div>
  </div>
  <div class="card p-4"><h2 class="h5">Verification responsibility</h2><p class="mb-0 text-muted">Open Doctor verification to inspect each professional profile, license number, qualification, clinic, and uploaded credential document before changing the status.</p></div>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-end mb-4"><div><span class="eyebrow">Professional review queue</span><h1 class="h3 mb-1">Doctor verification</h1><p class="text-muted mb-0">Review all submitted information and the credential file before approval.</p></div><span class="role-chip"><?=count($doctors)?> doctor profile(s)</span></div>
  <?php if (!$doctors): ?><div class="card p-4 text-muted">No doctor registration requests have been submitted.</div><?php endif ?>
  <?php foreach ($doctors as $doctor):
      $status = (string)($doctor['verification_status'] ?? 'pending');
      $credentialPath = (string)($doctor['credential_path'] ?? ''); ?>
    <article class="doctor-review-card">
      <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 mb-1"><?=h($doctor['full_name'] ?? 'Unnamed doctor')?></h2><div class="small text-muted"><?=h($doctor['email'] ?? '')?></div></div><span class="<?=admin_status_class($status)?>"><?=h(ucwords(str_replace('_', ' ', $status)))?></span></div>
      <div class="doctor-detail"><dl class="row mb-0"><div class="col-md-4"><dt>Specialization</dt><dd><?=h($doctor['specialization'] ?? $doctor['specialization_other'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Qualification</dt><dd><?=h($doctor['qualification'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Hospital / clinic</dt><dd><?=h($doctor['clinic_name'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>License number</dt><dd><?=h($doctor['license_number'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Credential type</dt><dd><?=h($doctor['credential_document_type'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Credential number</dt><dd><?=h($doctor['credential_number'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Issuing institution</dt><dd><?=h($doctor['issuing_institution'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Phone</dt><dd><?=h($doctor['phone'] ?? 'Not provided')?></dd></div><div class="col-md-4"><dt>Address</dt><dd><?=h($doctor['address'] ?? 'Not provided')?></dd></div></dl></div>
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3"><div><?php if ($credentialPath): ?><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="?action=credential_download&doctor_id=<?=urlencode((string)$doctor['id'])?>">Open credential document</a><?php else: ?><span class="text-danger small">No credential document uploaded.</span><?php endif ?><?php if (!empty($doctor['verification_reason'])): ?><div class="small text-muted mt-2">Previous review note: <?=h($doctor['verification_reason'])?></div><?php endif ?></div><form method="post" class="row g-2 align-items-end"><input type="hidden" name="form" value="review_doctor"><input type="hidden" name="doctor_id" value="<?=h($doctor['id'])?>"><div class="col-auto"><label class="form-label small mb-1">Decision</label><select class="form-select form-select-sm" name="verification_status"><option value="pending" <?=$status === 'pending' ? 'selected' : ''?>>Pending</option><option value="under_review" <?=$status === 'under_review' ? 'selected' : ''?>>Under review</option><option value="verified" <?=$status === 'verified' ? 'selected' : ''?>>Verified</option><option value="rejected" <?=$status === 'rejected' ? 'selected' : ''?>>Rejected</option></select></div><div class="col-auto"><label class="form-label small mb-1">Review note</label><input class="form-control form-control-sm" name="verification_reason" placeholder="Optional reason"></div><div class="col-auto"><button class="btn btn-sm btn-primary">Save decision</button></div></form></div>
    </article>
  <?php endforeach ?>
<?php endif ?>
</div>
<?php foot();
}
