-- CareNest secure medical file metadata and storage access repair.
-- Run once in Supabase SQL Editor. It is safe to re-run.

alter table public.medical_records
  add column if not exists original_file_name text,
  add column if not exists mime_type text,
  add column if not exists owner_id uuid references public.profiles(id) on delete cascade;

update public.medical_records set owner_id = patient_id where owner_id is null;
update public.medical_records
set original_file_name = coalesce(nullif(title, ''), nullif(record_title, ''), 'Medical record')
where original_file_name is null or original_file_name = '';
update public.medical_records set mime_type = file_type
where (mime_type is null or mime_type = '') and file_type is not null;
update public.medical_records
set processing_status = case when coalesce(file_path, '') = '' then 'legacy_missing_file' else 'stored' end
where processing_status is null or processing_status = '';

alter table public.medical_records enable row level security;
grant select, insert, update, delete on public.medical_records to authenticated;
drop policy if exists care_nest_records_owner_insert on public.medical_records;
create policy care_nest_records_owner_insert on public.medical_records for insert to authenticated
  with check (patient_id = auth.uid() and owner_id = auth.uid());

insert into storage.buckets (id, name, public)
values ('medical-records', 'medical-records', false)
on conflict (id) do update set public = false;

drop policy if exists care_nest_private_record_upload on storage.objects;
create policy care_nest_private_record_upload on storage.objects for insert to authenticated
  with check (bucket_id = 'medical-records' and (storage.foldername(name))[1] = auth.uid()::text);
drop policy if exists care_nest_private_record_read on storage.objects;
create policy care_nest_private_record_read on storage.objects for select to authenticated
  using (bucket_id = 'medical-records' and public.carenest_can_access_patient(((storage.foldername(name))[1])::uuid));
