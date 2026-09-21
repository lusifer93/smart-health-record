<?php
declare(strict_types=1);

/** Validate Supabase/PostgreSQL UUID identifiers used by medicine records. */
function valid_uuid(string $value): bool {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
}

function medicine_fields(array $input): array {
    $name=trim((string)($input['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Enter a medicine name up to 120 characters.');
    $status=in_array(($input['status'] ?? 'active'), ['active','inactive'], true) ? $input['status'] : 'active';
    $time=trim((string)($input['reminder_time'] ?? ''));
    if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time)) throw new RuntimeException('Choose a valid reminder time.');
    return [
        'name'=>$name, 'medicine_name'=>$name, 'dosage'=>trim((string)($input['dosage'] ?? '')),
        'frequency'=>trim((string)($input['frequency'] ?? '')), 'start_date'=>($input['start_date'] ?? '') ?: null,
        'end_date'=>($input['end_date'] ?? '') ?: null, 'reminder_time'=>$time ?: null,
        'notes'=>trim((string)($input['notes'] ?? '')), 'prescribed_by'=>trim((string)($input['prescribed_by'] ?? '')),
        'status'=>$status,
    ];
}

function medicine_portal(Supabase $sb, string $patientId): void {
    $edit=null; $editId=(string)($_GET['edit'] ?? '');
    // IDs are UUIDs in database/schema.sql; ctype_digit() made every edit action fail.
    if (valid_uuid($editId)) {
        $rows=$sb->db('GET','medicines','?select=*&id=eq.'.rawurlencode($editId).'&patient_id=eq.'.rawurlencode($patientId).'&limit=1');
        $edit=$rows[0] ?? null;
    }
    $items=$sb->db('GET','medicines','?select=*&patient_id=eq.'.rawurlencode($patientId).'&order=created_at.desc');
    head('Medicine management'); ?>
<div class="d-flex justify-content-between align-items-end mb-4"><div><span class="eyebrow">Medication plan</span><h1 class="display-title mb-0">My medicines</h1></div><span class="role-chip">Patient</span></div>
<div class="card p-4 mb-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0"><?=$edit ? 'Edit medicine' : 'Add medicine'?></h2><?php if($edit): ?><a class="small" href="?page=medicine_manage">Cancel</a><?php endif; ?></div>
<form method="post" class="row g-3"><input type="hidden" name="form" value="<?=$edit ? 'medicine_update' : 'medicine_create'?>"><input type="hidden" name="return_page" value="medicine_manage"><?php if($edit): ?><input type="hidden" name="id" value="<?=h($edit['id'])?>"><?php endif; ?>
<div class="col-md-6"><label class="form-label">Medicine name</label><input class="form-control" name="name" maxlength="120" required value="<?=h($edit['name'] ?? $edit['medicine_name'] ?? '')?>"></div>
<div class="col-md-6"><label class="form-label">Dosage</label><input class="form-control" name="dosage" value="<?=h($edit['dosage'] ?? '')?>"></div>
<div class="col-md-6"><label class="form-label">Frequency</label><input class="form-control" name="frequency" value="<?=h($edit['frequency'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">Reminder time</label><input class="form-control" type="time" name="reminder_time" value="<?=h($edit['reminder_time'] ?? '')?>"></div>
<div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=($edit['status'] ?? 'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=($edit['status'] ?? '')==='inactive'?'selected':''?>>Inactive</option></select></div>
<div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?=h($edit['notes'] ?? '')?></textarea></div>
<div class="col-12"><button class="btn btn-primary"><?=$edit ? 'Save changes' : 'Add medicine'?></button></div></form></div>
<div class="row g-3"><?php if(!$items): ?><div class="col-12"><div class="card p-4 text-muted">No medicines saved yet. Add one above or scan a prescription.</div></div><?php endif; foreach($items as $item): ?><div class="col-md-6"><div class="card p-4 h-100"><div class="d-flex justify-content-between"><h2 class="h5 mb-1"><?=h($item['name'] ?? $item['medicine_name'] ?? '')?></h2><span class="badge text-bg-light"><?=h($item['status'] ?? 'active')?></span></div><p class="text-muted mb-2"><?=h(trim(($item['dosage'] ?? '').' · '.($item['frequency'] ?? ''),' ·'))?></p><?php if(!empty($item['reminder_time'])): ?><div class="small text-muted">Reminder: <?=h($item['reminder_time'])?></div><?php endif; ?><a class="small" href="?page=medicine_manage&amp;edit=<?=rawurlencode((string)$item['id'])?>">Edit</a></div></div><?php endforeach; ?></div>
<?php foot();
}
