-- Security + reminder foundation for CareNest.
-- Safe to re-run.

alter table public.profiles
  add column if not exists phone text,
  add column if not exists address text,
  add column if not exists avatar_url text,
  add column if not exists verification_status text not null default 'pending',
  add column if not exists verification_reason text,
  add column if not exists verification_updated_at timestamptz,
  add column if not exists professional_title text,
  add column if not exists specialization text,
  add column if not exists specialization_other text,
  add column if not exists clinic_name text,
  add column if not exists qualification text,
  add column if not exists license_number text,
  add column if not exists credential_path text,
  add column if not exists credential_document_type text,
  add column if not exists credential_number text,
  add column if not exists issuing_institution text;

alter table public.medicines
  add column if not exists reminder_time text,
  add column if not exists instructions text,
  add column if not exists active boolean not null default true,
  add column if not exists reminder_timezone text not null default 'Asia/Kolkata';

alter table public.record_sharing
  add column if not exists status text not null default 'active';

create or replace function public.is_admin()
returns boolean
language sql
stable
security definer
set search_path = public as $$
  select exists (
    select 1 from public.profiles p
    where p.id = auth.uid()
      and p.role = 'admin'
  );
$$;

grant execute on function public.is_admin() to authenticated;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created after insert on auth.users
for each row execute procedure public.handle_new_user();

create table if not exists public.notifications (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.profiles(id) on delete cascade,
  type text not null,
  title text not null,
  body text not null,
  related_id uuid,
  read_at timestamptz,
  created_at timestamptz not null default now(),
  unique (user_id, type, related_id, title, body)
);

create index if not exists notifications_user_unread_idx
  on public.notifications (user_id, read_at, created_at desc);

alter table public.notifications enable row level security;

drop policy if exists notifications_self_read on public.notifications;
drop policy if exists notifications_self_write on public.notifications;

create policy notifications_self_read on public.notifications
  for select to authenticated
  using (user_id = auth.uid());

create policy notifications_self_write on public.notifications
  for update to authenticated
  using (user_id = auth.uid())
  with check (user_id = auth.uid());

-- Keep access broad enough for doctors and admins while restricting patient data to owner access.
create or replace function public.can_access_patient(pid uuid)
returns boolean
language sql
stable
security definer
set search_path = public as $$
  select auth.uid() = pid
    or exists (
      select 1 from public.record_sharing s
      where s.patient_id = pid
        and s.doctor_id = auth.uid()
        and coalesce(s.status, 'active') = 'active'
        and (s.expires_at is null or s.expires_at > now())
    )
    or public.is_admin();
$$;

grant execute on function public.can_access_patient(uuid) to authenticated;
