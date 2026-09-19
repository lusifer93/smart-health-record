# Smart Health Record

A polished PHP 8.1+ health-record mini project for Laragon. It uses Supabase Auth, PostgreSQL, Storage and Row Level Security (RLS).

## Start it

1. Copy this `smart-health-record` directory into `C:\laragon\www\`.
2. Copy `.env.example` to `.env` and add the **project URL** and **anon/publishable key** from Supabase Dashboard → Project Settings → API. Never put the database password in this file.
3. In Supabase SQL Editor, run [`database/schema.sql`](database/schema.sql). It safely creates/aligns the seven tables, a private `medical-records` bucket and RLS policies.
4. Browse to `http://localhost/smart-health-record/`.

## Testing

Register as a patient. With email confirmation disabled for this demo, a new registration immediately opens the dashboard. A profile is automatically created at signup. The patient and doctor entry points are intentionally separate; doctor accounts need an administrator to verify their `profiles.role` as `doctor`.

The project includes an upgraded CareNest visual design: a soft clinical background, patient-first onboarding, AI insight entry point, and a clinician portal entry point.

## New mini-project features

- AI insights display as short, plain-language bullet points.
- Prescription photos can be scanned into medicine entries when a server-only `OPENAI_API_KEY` is present in `.env`. Patients must verify every extracted medicine and dose.
- Medicine cards support optional in-browser reminders while the CareNest page stays open. Browser permission is required.
- Doctor sharing uses the existing `record_sharing.shared_at` field and has been aligned with the app using `database/share-fix.sql`.

## AI reports

Add an `OPENAI_API_KEY` to `.env` only if you want the report generator to use OpenAI. Without it, the app saves a transparent rule-based health summary instead. Keep any AI key server-side; it is never sent to the browser.

## Security notes

The browser never receives a service-role key. Supabase access uses the signed-in user token and RLS. Medical files are stored in a private bucket and downloaded through short-lived signed links.
