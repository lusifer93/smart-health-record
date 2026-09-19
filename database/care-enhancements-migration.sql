-- CareNest enhancements: professional doctor details, medicine workflow and notes.
-- Run after doctor-portal-migration.sql in Supabase SQL Editor.

alter table public.profiles
  add column if not exists professional_title text,
  add column if not exists specialization text,
  add column if not exists specialization_other text,
  add column if not exists clinic_name text,
  add column if not exists qualification text,
  add column if not exists license_number text,
  add column if not exists credential_path text,
  add column if not exists credential_document_type text,
  add column if not exists credential_number text,
  add column if not exists issuing_institution text,
  add column if not exists verification_status text not null default 'pending',
  add column if not exists verification_updated_at timestamptz;

alter table public.medicines
  add column if not exists end_date date,
  add column if not exists prescribed_by text,
  add column if not exists status text not null default 'active';

alter table public.doctor_notes
  add column if not exists note_type text not null default 'Clinical note';

create index if not exists medicines_patient_status_idx on public.medicines(patient_id, status);
create index if not exists profiles_doctor_verification_idx on public.profiles(role, verification_status);

-- Doctors cannot set their own verification status. The existing self-update policy
-- must continue to restrict profile writes to normal professional profile fields.
-- Verification is reserved for an administrator/reviewer workflow.
