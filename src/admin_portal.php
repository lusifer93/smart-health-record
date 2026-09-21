<?php
declare(strict_types=1);

function require_admin(): void { require_role('admin'); }

function admin_portal(): void {
    require_admin(); $sb=new Supabase(); $page=(string)($_GET['page'] ?? 'admin_dashboard');
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form'] ?? '')==='review_doctor') {
        try {
            $doctorId=(string)($_POST['doctor_id'] ?? '');
            $status=(string)($_POST['verification_status'] ?? '');
            if (!preg_match('/^[0-9a-f-]{36}$/i',$doctorId)) throw new RuntimeException('Invalid doctor selected.');
            $sb->rpc('carenest_set_doctor_verification',['target_doctor'=>$doctorId,'next_status'=>$status,'review_reason'=>trim((string)($_POST['verification_reason'] ?? ''))]);
            flash('success','Doctor verification status updated.');
        } catch(Throwable $e) { flash('danger',safe_error_message($e)); }
        redirect('?page=admin_verification');
    }
    $tabs=['admin_dashboard'=>'Overview','admin_verification'=>'Doctor verification'];
    if (!isset($tabs[$page])) $page='admin_dashboard';
    $doctors=$sb->db('GET','profiles','?select=id,full_name,email,specialization,clinic_name,qualification,license_number,credential_path,credential_document_type,verification_status,verification_reason,verification_updated_at&role=eq.doctor&order=created_at.desc');
    head('Administrator · '.$tabs[$page]); ?>
<style>.admin-shell{max-width:1120px;margin:auto}.admin-nav{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}.admin-nav a{padding:.55rem .85rem;border-radius:10px;background:#fff;color:#173044;text-decoration:none;border:1px solid #d8e8e4}.admin-nav a.active{background:#173044;color:#fff}.status-verified{color:#147a50;font-weight:700}.status-pending{color:#9a6400;font-weight:700}</style>
<div class="admin-shell"><div class="d-flex justify-content-between align-items-center mb-3"><div><span class="eyebrow">Restricted area</span><h1 class="h3 mb-0">CareNest administration</h1></div><a class="btn btn-sm btn-outline-primary" href="?action=logout">Sign out</a></div><nav class="admin-nav"><?php foreach($tabs as $key=>$label): ?><a class="<?=$page===$key?'active':''?>" href="?page=<?=$key?>"><?=h($label)?></a><?php endforeach ?></nav>
<?php if($page==='admin_dashboard'): ?><div class="row g-3"><div class="col-md-4"><div class="card p-4"><div class="small text-muted">DOCTOR PROFILES</div><div class="metric"><?=count($doctors)?></div></div></div><div class="col-md-8"><div class="card p-4"><h2 class="h5">Verification responsibility</h2><p class="mb-0 text-muted">Only administrator accounts can update a clinician’s verification status. A certificate upload alone never verifies a doctor.</p></div></div></div>
<?php else: ?><div class="card p-4"><h2 class="h5">Review doctor credentials</h2><p class="small text-muted">Review professional information and, where supplied, the private credential document before changing status.</p><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Doctor</th><th>Professional details</th><th>Status</th><th>Review</th></tr></thead><tbody><?php foreach($doctors as $d): ?><tr><td><strong><?=h($d['full_name'])?></strong><div class="small text-muted"><?=h($d['email'])?></div></td><td><div><?=h($d['specialization'] ?? 'Not provided')?></div><div class="small text-muted"><?=h($d['clinic_name'] ?? '')?> <?=h($d['qualification'] ?? '')?></div><?php if(!empty($d['credential_path'])): ?><a class="small" href="?action=credential_download&doctor_id=<?=urlencode($d['id'])?>">View private credential</a><?php endif ?></td><td><span class="<?=($d['verification_status'] ?? '')==='verified'?'status-verified':'status-pending'?>"><?=h(str_replace('_',' ',(string)($d['verification_status'] ?? 'verification_required')))?></span></td><td><form method="post" class="d-grid gap-2"><input type="hidden" name="form" value="review_doctor"><input type="hidden" name="doctor_id" value="<?=h($d['id'])?>"><select class="form-select form-select-sm" name="verification_status"><?php foreach(['verification_required'=>'Verification required','pending'=>'Pending','under_review'=>'Under review','verified'=>'Verified','rejected'=>'Rejected'] as $value=>$label): ?><option value="<?=$value?>" <?=$value===($d['verification_status'] ?? '')?'selected':''?>><?=$label?></option><?php endforeach ?></select><input class="form-control form-control-sm" name="verification_reason" value="<?=h($d['verification_reason'] ?? '')?>" placeholder="Optional reviewer reason"><button class="btn btn-sm btn-primary">Save review</button></form></td></tr><?php endforeach ?></tbody></table></div></div><?php endif ?></div><?php foot();
}
