-- CareNest structured AI analysis foundation.
-- Safe to run repeatedly. Does not alter or drop existing data.

create extension if not exists pgcrypto;

create table if not exists public.carenest_ai_analyses (
  id uuid primary key default gen_random_uuid(),
  patient_id uuid not null references public.profiles(id) on delete cascade,
  analysis_type text not null check (analysis_type in ('prescription','report')),
  source_file_path text,
  source_file_name text,
  measurements jsonb not null default '[]'::jsonb,
  medicines jsonb not null default '[]'::jsonb,
  summary text,
  status text not null default 'pending' check (status in ('pending','completed','failed')),
  error_message text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists carenest_ai_analyses_patient_created_idx
  on public.carenest_ai_analyses(patient_id, created_at desc);

alter table public.carenest_ai_analyses enable row level security;

drop policy if exists carenest_ai_analysis_patient_read on public.carenest_ai_analyses;
drop policy if exists carenest_ai_analysis_patient_insert on public.carenest_ai_analyses;
drop policy if exists carenest_ai_analysis_patient_update on public.carenest_ai_analyses;
drop policy if exists carenest_ai_analysis_shared_doctor_read on public.carenest_ai_analyses;

create policy carenest_ai_analysis_patient_read on public.carenest_ai_analyses
  for select to authenticated using (patient_id = auth.uid());

create policy carenest_ai_analysis_patient_insert on public.carenest_ai_analyses
  for insert to authenticated with check (patient_id = auth.uid());

create policy carenest_ai_analysis_patient_update on public.carenest_ai_analyses
  for update to authenticated using (patient_id = auth.uid()) with check (patient_id = auth.uid());

create policy carenest_ai_analysis_shared_doctor_read on public.carenest_ai_analyses
  for select to authenticated using (
    exists (
      select 1 from public.record_sharing rs
      where rs.patient_id = carenest_ai_analyses.patient_id
        and rs.doctor_id = auth.uid()
        and coalesce(rs.status, 'active') = 'active'
        and (rs.expires_at is null or rs.expires_at > now())
    )
  );

comment on table public.carenest_ai_analyses is 'Code-validated measurements and AI-assisted wording; never stores API keys.';
