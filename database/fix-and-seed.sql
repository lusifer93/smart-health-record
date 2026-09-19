-- Already applied to your Supabase project on 2026-09-16.
-- This file documents the minimum permission repair used for the 403 error.
grant usage on schema public to authenticated;
grant select, insert, update, delete on table public.profiles, public.medical_records, public.health_metrics, public.medicines, public.appointments, public.record_sharing, public.ai_reports to authenticated;
grant usage, select on all sequences in schema public to authenticated;

-- Sample patient data was seeded for testpatient123@example.com only.
-- It is intentionally fake and may be deleted later from Supabase Table Editor.
