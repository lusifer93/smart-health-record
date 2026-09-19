-- Already applied to your Supabase project on 2026-09-17.
-- Matches the app's sharing timestamp with the existing database table.
alter table public.record_sharing add column if not exists created_at timestamptz;
update public.record_sharing set created_at = shared_at where created_at is null;
alter table public.record_sharing alter column created_at set default now();
