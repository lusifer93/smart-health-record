-- CareNest: admin-only clinician verification. Safe to run once or repeatedly.
alter table public.profiles
  add column if not exists verification_reason text,
  add column if not exists verification_reviewed_by uuid references public.profiles(id),
  add column if not exists verification_updated_at timestamptz;

create or replace function public.carenest_is_admin()
returns boolean language sql stable security definer set search_path=public as $$
  select exists(select 1 from public.profiles where id=auth.uid() and role='admin');
$$;

create or replace function public.carenest_set_doctor_verification(
  target_doctor uuid, next_status text, review_reason text default null
)
returns void language plpgsql security definer set search_path=public as $$
begin
  if not public.carenest_is_admin() then raise exception 'Administrator access is required'; end if;
  if next_status not in ('verification_required','pending','under_review','verified','rejected') then raise exception 'Invalid verification status'; end if;
  if not exists(select 1 from public.profiles where id=target_doctor and role='doctor') then raise exception 'Doctor profile was not found'; end if;
  update public.profiles set verification_status=next_status, verification_reason=nullif(trim(review_reason),''), verification_reviewed_by=auth.uid(), verification_updated_at=now() where id=target_doctor;
end;
$$;

revoke all on function public.carenest_is_admin() from public;
revoke all on function public.carenest_set_doctor_verification(uuid,text,text) from public;
grant execute on function public.carenest_is_admin() to authenticated;
grant execute on function public.carenest_set_doctor_verification(uuid,text,text) to authenticated;

-- Prevent client-side profile PATCH calls from changing role or verification fields.
revoke update on public.profiles from authenticated;
grant update (full_name, phone, address, professional_title, specialization, specialization_other, clinic_name, qualification, license_number, credential_path, credential_document_type, credential_number, issuing_institution) on public.profiles to authenticated;

-- Credential objects use the existing private bucket. Only their owner and admins may download.
drop policy if exists care_nest_admin_credential_download on storage.objects;
create policy care_nest_admin_credential_download on storage.objects for select to authenticated
  using (bucket_id='medical-records' and public.carenest_is_admin());
