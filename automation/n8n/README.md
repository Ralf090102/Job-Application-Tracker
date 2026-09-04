# n8n Workflow — Auto-Apply Ingest

`jat-auto-apply-ingest.json` is v2 Phase 4's one workflow, versioned here alongside the code it
drives rather than living only in n8n's own UI (JAT-Roadmap-AutoApply.md Phase 4). Deliberately
thin, per the roadmap's Architecture/Decided-Against sections: **Schedule Trigger (daily) → HTTP
Request (JSearch) → HTTP Request (POST to `/api/auto-apply/ingest`)** — nothing else. All
matching/tailoring/rendering stays server-side in Laravel; n8n's only job is scheduling +
fetching + forwarding.

Secrets are redacted in this file (`YOUR_JSEARCH_API_KEY`, `YOUR_AUTO_APPLY_INGEST_TOKEN`) — this
is a public-ish JSON file in git, and n8n's plain HTTP Request header fields aren't backed by its
credential vault here, so real values never get committed. Fill them in after importing.

## Import

1. In n8n: **Workflows → ⋯ → Import → From File** (or drag the file onto the canvas), pick
   `jat-auto-apply-ingest.json`.
2. Open the **Search JSearch** node → Headers → `x-rapidapi-key` → paste your real JSearch
   (RapidAPI) key — the same value as this project's own `JSEARCH_API_KEY` in `.env`.
3. Open the **POST to Laravel Ingest** node → Headers → `X-Ingest-Token` → paste this project's
   `AUTO_APPLY_INGEST_TOKEN` value from `.env` (they must match exactly — Laravel's
   `VerifyAutoApplyIngestToken` middleware rejects anything else with a 401).
4. Confirm the ingest node's URL actually reaches your running Laravel instance:
   - n8n running in **Docker Desktop on Windows**, Laravel via `php artisan serve`: use
     `http://host.docker.internal:8000/...` (what this file ships with) — a plain `localhost`
     from inside the container resolves to the container itself, not the host.
   - n8n running directly on the host (npm install, no Docker): use `http://localhost:8000/...`
     instead.
5. **Publish** the workflow. Its Schedule Trigger only fires once activated — building/testing it
   unpublished never runs on a schedule.

## Notes

- **`date_posted=week`, not `all` or `3days`.** Confirmed live against this project's actual
  JSearch subscription: `3days` returned zero results for a real query that `week` and `all` both
  returned real postings for — too narrow to be a reliable daily cron. `all` works too but
  re-fetches the same historical postings every single day forever; `week` is the better balance
  (Laravel's `posting_url` unique constraint makes either choice safe either way — a re-fetched
  duplicate is just silently skipped, never double-inserted).
- **Query/location are static, not read from `job_search_criteria`.** Deliberate, matching the
  roadmap's "n8n stays cron + one call" framing (Decided Against: n8n never becomes the
  orchestrator) — n8n doesn't query Laravel's DB first. If `job_search_criteria`'s
  `position_keywords`/`location` change, update the **Search JSearch** node's `query` parameter to
  match by hand. Multiple saved search profiles are explicitly out of scope for v1
  (JAT-Roadmap-AutoApply.md Phase 1).
- **Verified end-to-end 2026-09-04**: a real manual execution — real JSearch call, real posting
  returned, real `POST /api/auto-apply/ingest` call from inside the n8n container to Laravel on
  the host — produced a real `matched` `AutoApplyCandidate` row. Exit criteria's "leave it running
  overnight" check is the one thing that still needs a real elapsed day once published.
