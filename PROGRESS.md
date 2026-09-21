# PROGRESS

## Phase 1: Security foundation and bugs
- [x] Fixed the medicine UUID edit regression in `src/medicine_portal.php`.
- [x] Confirmed `.env` stays ignored via `.gitignore` and `.env.example` remains available as the template.
- [x] Added a repo-side security/reminder foundation migration file under `database/` to cover missing columns and safer role/notification primitives.
- [ ] Add server-side role checks to every action and route.
- [ ] Harden file upload endpoints and direct private file access.
- [ ] Fix doctor shared-record rendering for edge-case values and notes.

## Phase 2: Doctor identity and verification
- [ ] Add explicit doctor verification metadata fields and admin review workflow.
- [ ] Implement verified clinician badge logic and polished clinician profile card.
- [ ] Add minimal admin account creation instructions.

## Phase 3: Appointments and notifications
- [ ] Add appointment request workflow and notification center persistence.
- [ ] Add reviewed/approved/rejected appointment state transitions.
- [ ] Separate email from in-app notification logic and document SMTP needs.

## Phase 4: Medicines and reminders
- [ ] Diagnose reminder trigger root cause and add timezone-aware scheduling support.
- [ ] Add reliable reminder generation and deduplication.
- [ ] Provide CLI scheduling instructions for Windows/Laragon Task Scheduler.

## Phase 5: Prescriptions
- [ ] Add secure prescription attachment and viewing flow.
- [ ] Store AI analysis status and present results separately from the original file.
- [ ] Enforce doctor access checks for shared patient prescriptions.

## Phase 6: GUI redesign
- [ ] Rework layouts into a modern Bootstrap 5 shell and responsive dashboards.

## Notes for local execution
- This repo cannot run here in the local Laragon environment, so code is being prepared in the repository itself.
- To validate locally, copy `.env.example` to `.env`, run the SQL migrations in Supabase, and check PHP syntax plus browser behavior.
- Keep secrets out of commits and logs.
