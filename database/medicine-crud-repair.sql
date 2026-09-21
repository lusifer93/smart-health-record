-- CareNest medicines CRUD repair: matches the live bigint-ID medicines table.
-- Run once in the Supabase SQL Editor.

alter table public.medicines
  add column if not exists status text not null default 'active'
  check (status in ('active', 'inactive'));

update public.medicines set status = 'active' where status is null;

alter table public.medicines enable row level security;
grant select, insert, update, delete on public.medicines to authenticated;

drop policy if exists "medicines patient update" on public.medicines;
drop policy if exists "medicines patient delete" on public.medicines;

create policy "medicines patient update" on public.medicines
  for update to authenticated
  using (patient_id = auth.uid())
  with check (patient_id = auth.uid());

create policy "medicines patient delete" on public.medicines
  for delete to authenticated
  using (patient_id = auth.uid());
