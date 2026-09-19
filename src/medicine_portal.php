<?php
declare(strict_types=1);

function medicine_fields(array $input): array {
    $name=trim((string)($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Enter a medicine name up to 120 characters.');
    $status=in_array(($input['status'] ?? 'active'), ['active','inactive'], true) ? $input['status'] : 'active';
    $time=trim((string)($input['reminder_time'] ?? ''));
    if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time)) throw new RuntimeException('Choose a valid reminder time.');
    return [
        'name'=>$name, 'medicine_name'=>$name, 'dosage'=>trim((string)($input['dosage'] ?? '')),
        'frequency'=>trim((string)($input['frequency'] ?? '')), 'start_date'=>$input['start_date'] ?: null,
        'end_date'=>$input['end_date'] ?: null, 'reminder_time'=>$time ?: null,
        'notes'=>trim((string)($input['notes'] ?? '')), 'prescribed_by'=>trim((string)($input['prescribed_by'] ?? '')),
        'status'=>$status,
    ];
}

function medicine_portal(Supabase $sb, string $patientId): void {
    $edit=null; $editId=(string)($_GET['edit'] ?? '');
    if ($editId !== '' && ctype_digit($editId)) {
        $rows=$sb->db('GET','medicines','?select=*&id=eq.'.rawurlencode($editId).'&patient_id=eq.'.rawurlencode($patientId).'&limit=1');
        $edit=$rows[0] ?? null;
    }
    $items=$sb->db('GET','medicines','?select=*&patient_id=eq.'.rawurlencode($patientId).'&order=created_at.desc');
    head('Medicine management'); ?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><span class="eyebrow">Medication plan</span><h1 class="display-title mb-0">My medicines</h1></div><span class="role-chip"><?=count($items)?> saved</span></div>
<div class="card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><?=$edit ? 'Edit medicine' : 'Add medicine'?></h2><?php if($edit): ?><a class="small" href="?page=medicine_manage">Cancel edit</a><?php endif ?></div><form method="post" class="row g-3"><input type="hidden" name="form" value="<?=$edit?'medicine_update':'medicine_create'?>"><input type="hidden" name="return_page" value="medicine_manage"><?php if($edit): ?><input type="hidden" name="id" value="<?=h($edit['id'])?>"><?php endif ?><div class="col-md-4"><label class="form-label small fw-bold">Medicine name</label><input class="form-control" name="name" value="<?=h($edit['name'] ?? $edit['medicine_name'] ?? '')?>" required></div><div class="col-md-2"><label class="form-label small fw-bold">Dosage</label><input class="form-control" name="dosage" value="<?=h($edit['dosage'] ?? '')?>" placeholder="500 mg"></div><div class="col-md-3"><label class="form-label small fw-bold">Frequency</label><input class="form-control" name="frequency" value="<?=h($edit['frequency'] ?? '')?>" placeholder="Twice daily"></div><div class="col-md-3"><label class="form-label small fw-bold">Reminder</label><input class="form-control" name="reminder_time" type="time" value="<?=h($edit['reminder_time'] ?? '')?>"></div><div class="col-md-3"><label class="form-label small fw-bold">Start date</label><input class="form-control" name="start_date" type="date" value="<?=h($edit['start_date'] ?? '')?>"></div><div class="col-md-3"><label class="form-label small fw-bold">End date</label><input class="form-control" name="end_date" type="date" value="<?=h($edit['end_date'] ?? '')?>"></div><div class="col-md-3"><label class="form-label small fw-bold">Prescribed by</label><input class="form-control" name="prescribed_by" value="<?=h($edit['prescribed_by'] ?? '')?>" placeholder="Dr. name"></div><div class="col-md-3"><label class="form-label small fw-bold">Status</label><select class="form-select" name="status"><option value="active" <?=($edit['status'] ?? 'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($edit['status'] ?? '')==='inactive'?'selected':''?>>Inactive</option></select></div><div class="col-12"><label class="form-label small fw-bold">Instructions</label><input class="form-control" name="notes" value="<?=h($edit['notes'] ?? '')?>" placeholder="Take after food, with water, or other instructions"></div><div><button class="btn btn-primary"><?=$edit?'Save changes':'Add medicine'?></button></div></form></div>
<div class="row g-3"><?php if(!$items): ?><div class="col-12"><div class="card p-4 text-muted">No medicines saved yet. Add one above or scan a prescription.</div></div><?php endif; foreach($items as $m): $name=$m['name'] ?? $m['medicine_name'] ?? 'Medicine'; ?><div class="col-md-6"><article class="card p-4 medicine-card h-100"><div class="d-flex justify-content-between gap-2"><strong><?=h($name)?></strong><span class="role-chip"><?=h($m['status'] ?? 'active')?></span></div><div class="small text-muted mt-2"><?=h($m['dosage'] ?? 'Dose not recorded')?> · <?=h($m['frequency'] ?? 'Schedule not recorded')?></div><?php if(!empty($m['reminder_time'])): ?><div class="small mt-2">Reminder: <?=h(date('g:i A',strtotime($m['reminder_time'])))?></div><?php endif ?><div class="small text-muted mt-2"><?=h($m['notes'] ?? '')?></div><div class="d-flex gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" href="?page=medicine_manage&edit=<?=urlencode((string)$m['id'])?>">Edit</a><form method="post" onsubmit="return confirm('Remove this medicine from your list?')"><input type="hidden" name="form" value="medicine_delete"><input type="hidden" name="return_page" value="medicine_manage"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></div></article></div><?php endforeach ?></div>
<script>setInterval(()=>{if(!('Notification'in window)||Notification.permission!=='granted')return;const t=new Date().toTimeString().slice(0,5);document.querySelectorAll('[data-reminder]').forEach(x=>{if(x.dataset.reminder===t&&!sessionStorage.getItem('med-'+x.dataset.id+t)){new Notification('CareNest reminder',{body:x.dataset.name});sessionStorage.setItem('med-'+x.dataset.id+t,'1')}})},30000)</script>
<?php foot();
}
