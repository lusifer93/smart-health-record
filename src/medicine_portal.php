<?php
declare(strict_types=1);

/** The live medicines table uses a bigint ID. */
function valid_medicine_id(string $value): bool {
    return ctype_digit($value) && (int) $value > 0;
}

function medicine_fields(array $input): array {
    $name=trim((string)($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Enter a medicine name up to 120 characters.');
    $status=in_array(($input['status'] ?? 'active'), ['active','inactive'], true) ? $input['status'] : 'active';
    $time=trim((string)($input['reminder_time'] ?? ''));
    // PostgreSQL returns a time column as HH:MM:SS; browsers submit HH:MM.
    if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$time)) throw new RuntimeException('Choose a valid reminder time.');
    return [
        'name'=>$name, 'medicine_name'=>$name, 'dosage'=>trim((string)($input['dosage'] ?? '')),
        'frequency'=>trim((string)($input['frequency'] ?? '')), 'start_date'=>($input['start_date'] ?? '') ?: null,
        'end_date'=>($input['end_date'] ?? '') ?: null, 'reminder_time'=>$time ?: null,
        'notes'=>trim((string)($input['notes'] ?? '')),
        'status'=>$status,
    ];
}

function medicine_portal(Supabase $sb, string $patientId): void {
    $edit=null; $editId=(string)($_GET['edit'] ?? '');
    if (valid_medicine_id($editId)) {
        $rows=$sb->db('GET','medicines','?select=*&id=eq.'.rawurlencode($editId).'&patient_id=eq.'.rawurlencode($patientId).'&limit=1');
        $edit=$rows[0] ?? null;
    }
    // This confirmation screen is also protected by patient_id, so one patient can never remove another's record.
    $deleteCandidate=null; $deleteId=(string)($_GET['delete'] ?? '');
    if (valid_medicine_id($deleteId)) {
        $rows=$sb->db('GET','medicines','?select=id,name,medicine_name&id=eq.'.rawurlencode($deleteId).'&patient_id=eq.'.rawurlencode($patientId).'&limit=1');
        $deleteCandidate=$rows[0] ?? null;
    }
    $items=$sb->db('GET','medicines','?select=*&patient_id=eq.'.rawurlencode($patientId).'&order=created_at.desc');
    head('Medicine management'); ?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><span class="eyebrow">Medication plan</span><h1 class="display-title mb-0">My medicines</h1></div><span class="role-chip">Patient</span></div>
<?php if($deleteCandidate): ?><div class="alert alert-warning card p-4 mb-4"><h2 class="h5">Remove <?=h($deleteCandidate['name'] ?? $deleteCandidate['medicine_name'] ?? 'this medicine')?>?</h2><p class="mb-3">This permanently removes only this medicine from your own plan. This cannot be undone.</p><form method="post" class="d-flex gap-2"><input type="hidden" name="form" value="medicine_delete"><input type="hidden" name="return_page" value="medicine_manage"><input type="hidden" name="id" value="<?=h((string)$deleteCandidate['id'])?>"><input type="hidden" name="confirm_delete" value="1"><button class="btn btn-danger">Yes, remove medicine</button><a class="btn btn-outline-secondary" href="?page=medicine_manage">Cancel</a></form></div><?php endif; ?>
<div class="card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><?=$edit ? 'Edit medicine' : 'Add medicine'?></h2><?php if($edit): ?><a class="small" href="?page=medicine_manage">Cancel</a><?php endif; ?></div>
<form method="post" class="row g-3"><input type="hidden" name="form" value="<?=$edit ? 'medicine_update' : 'medicine_create'?>"><input type="hidden" name="return_page" value="medicine_manage"><?php if($edit): ?><input type="hidden" name="id" value="<?=h($edit['id'])?>"><?php endif; ?>
<div class="col-md-6"><label class="form-label">Medicine name</label><input class="form-control" name="name" maxlength="120" required value="<?=h($edit['name'] ?? $edit['medicine_name'] ?? '')?>"></div>
<div class="col-md-6"><label class="form-label">Dosage</label><input class="form-control" name="dosage" value="<?=h($edit['dosage'] ?? '')?>"></div>
<div class="col-md-6"><label class="form-label">Frequency</label><input class="form-control" name="frequency" value="<?=h($edit['frequency'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">Start date</label><input class="form-control" type="date" name="start_date" value="<?=h($edit['start_date'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">End date</label><input class="form-control" type="date" name="end_date" value="<?=h($edit['end_date'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">Reminder time</label><input class="form-control" type="time" name="reminder_time" value="<?=h($edit['reminder_time'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=($edit['status'] ?? 'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($edit['status'] ?? '')==='inactive'?'selected':''?>>Inactive</option></select></div>
<div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?=h($edit['notes'] ?? '')?></textarea></div>
<div class="col-12"><button class="btn btn-primary"><?=$edit ? 'Save changes' : 'Add medicine'?></button></div></form></div>
<div class="row g-3"><?php if(!$items): ?><div class="col-12"><div class="card p-4 text-muted">No medicines saved yet. Add one above or scan a prescription.</div></div><?php endif; foreach($items as $item): ?><div class="col-md-6"><div class="card p-4 h-100"><div class="d-flex justify-content-between"><h2 class="h5 mb-1"><?=h($item['name'] ?? $item['medicine_name'] ?? '')?></h2><span class="badge <?=($item['status'] ?? 'active')==='inactive'?'text-bg-secondary':'text-bg-success'?>"><?=h($item['status'] ?? 'active')?></span></div><p class="text-muted mb-2"><?=h(trim(($item['dosage'] ?? '').' · '.($item['frequency'] ?? ''),' ·'))?></p><?php if(!empty($item['reminder_time'])): ?><div class="small text-muted">Reminder: <?=h($item['reminder_time'])?></div><?php endif; ?><div class="d-flex gap-3 mt-3"><a class="small" href="?page=medicine_manage&amp;edit=<?=rawurlencode((string)$item['id'])?>">Edit</a><a class="small text-danger" href="?page=medicine_manage&amp;delete=<?=rawurlencode((string)$item['id'])?>">Remove</a></div></div></div><?php endforeach; ?></div>
<?php foot();
}
