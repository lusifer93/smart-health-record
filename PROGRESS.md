# CareNest implementation progress

## AI analysis phase — prepared

- Added `database/ai-analysis-structured.sql`, a safe idempotent migration for patient-owned prescription/report analyses with shared-doctor read RLS.
- Added `src/ai_analysis.php` with code-first reference ranges and Normal/High/Low classification helpers.
- The OpenAI key remains server-side only; no key is stored in the database or frontend.

## Required next local steps

1. Pull `gui-redesign`.
2. Apply `database/ai-analysis-structured.sql` in Supabase SQL Editor.
3. Wire `src/ai_analysis.php` into the existing POST/report and prescription display flows.
4. Add the AI request as an optional wording layer; preserve code-based statuses if it fails.
5. Run PHP lint and test real uploads in Laragon.

## Existing configuration

- `OPENAI_API_KEY` belongs only in the untracked `.env` file.
- `OPENAI_MODEL` may be set to `gpt-4.1-mini`.
- Never commit `.env`, keys, credentials, or real patient data.
