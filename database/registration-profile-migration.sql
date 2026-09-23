-- CareNest: registration and profile fields. Safe to run repeatedly.
alter table public.profiles
  add column if not exists nearest_hospital text,
  add column if not exists verification_status text not null default 'pending';

-- Keep public self-registration as patient-only. Doctor records are created by the
-- server-side registration flow and remain pending until an administrator reviews them.
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
  insert into public.profiles (id, email, full_name, phone, address, nearest_hospital)
  values (
    new.id,
    new.email,
    coalesce(new.raw_user_meta_data->>'full_name', ''),
    coalesce(new.raw_user_meta_data->>'phone', ''),
    coalesce(new.raw_user_meta_data->>'address', ''),
    coalesce(new.raw_user_meta_data->>'nearest_hospital', '')
  )
  on conflict (id) do update set
    email = excluded.email,
    full_name = coalesce(nullif(excluded.full_name, ''), profiles.full_name),
    phone = coalesce(nullif(excluded.phone, ''), profiles.phone),
    address = coalesce(nullif(excluded.address, ''), profiles.address),
    nearest_hospital = coalesce(nullif(excluded.nearest_hospital, ''), profiles.nearest_hospital);
  return new;
end;
$$;

-- Doctor credential fields are already present in the verification migration; these
-- grants make the registration flow safe for authenticated profile updates.
revoke update on public.profiles from authenticated;
grant update (
  full_name, phone, address, nearest_hospital,
  professional_title, specialization, specialization_other,
  clinic_name, qualification, license_number,
  credential_path, credential_document_type, credential_number,
  issuing_institution
) on public.profiles to authenticated;
