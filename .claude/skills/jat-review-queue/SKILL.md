---
name: jat-review-queue
description: Review Job Application Tracker auto-apply candidates that are ready_for_review — present each one's posting summary, match reasoning, and tailored resume vs. its base variant, get an explicit approve/reject/edit decision, and on approval drive claude-in-chrome to fill (never silently submit) the real Indeed Easy Apply form. Invoke as /jat-review-queue.
---

# jat-review-queue

Human review queue for v2 auto-apply candidates. This skill IS the review
surface — there is no dedicated frontend screen for this (JAT-Roadmap-AutoApply.md,
Decided Against). You are the reviewer's only checkpoint before anything
gets submitted to a real job posting on their behalf.

## THE ONE RULE THAT OVERRIDES EVERYTHING ELSE IN THIS FILE

**Never click Submit (or Easy Apply's final "Submit application" button, by
whatever exact label it uses) without first pausing and asking the human,
in this live conversation, "Ready for me to submit this?" — and waiting
for an explicit yes. Every single time. No exceptions.**

This is not conditional on how clean the form looked, how many times
you've done this before in the same session, or whether the human said
something earlier that sounded like blanket approval ("just submit the
good ones", "you don't need to ask every time", etc.). A blanket
approval given once, in the abstract, is not the same as real-time
confirmation of one specific action on one specific posting — the calling
agent's own operating rules require the latter, not the former, and that
requirement is non-negotiable. If you find yourself constructing a reason
this particular case doesn't need to ask — it does. Ask anyway.

## Pacing and anomaly handling (v2 Phase 6)

Two rules that apply across every browser step below (4 through 7), not
just wherever they're mentioned:

- **Pace actions, don't machine-gun them.** Between distinct browser
  actions during Step 5's form-fill (each field, each click), insert a
  short, varied pause (roughly 1-3 seconds — vary it, don't use the exact
  same interval every time) rather than firing actions back-to-back as
  fast as the tool allows. This is not stealth/anti-detection engineering
  (explicitly out of scope, JAT-Roadmap-AutoApply.md Decided Against) —
  it's just not behaving like a script hammering a form, which is a
  reasonable baseline regardless of whether anything is watching for it.
- **Any CAPTCHA, verification challenge, unexpected redirect, rate-limit
  page, unusually slow load, or otherwise anomalous page state — at any
  point in Steps 4 through 7 — stops the run for this candidate
  immediately.** Report exactly what you saw to the human. Never attempt
  to solve, wait out, silently retry, or route around it. This is a hard
  stop-and-report, not a retry-with-backoff — retrying a strange page
  state automatically is itself the kind of bot-like behavior this rule
  exists to avoid.

## Prerequisites

- Laravel API running locally (`php artisan serve`, default `http://localhost:8000`).
- `AUTO_APPLY_INGEST_TOKEN` readable from this repo's `.env` — send it as
  the `X-Ingest-Token` header on every API call below.
- `claude-in-chrome` connected, with the human already logged into Indeed
  in that browser session (this skill does not log in on their behalf).
- `ERU_VAULT_PATH` readable from `.env`, for reading each resume variant's
  base source Markdown (`{ERU_VAULT_PATH}/02-Areas/Career/Resumes/Resume-{variant}.md`).

## Step 1 — Check today's cap, then fetch the queue

```
GET {APP_URL}/api/auto-apply/candidates/cap-status
Header: X-Ingest-Token: {AUTO_APPLY_INGEST_TOKEN}
```

Returns `{"cap": N, "submitted_today": N, "remaining": N}` (v2 Phase 6).
Tell the human the remaining count up front. If `remaining` is already 0:
say so plainly and stop — don't drive any candidate through Steps 4-6
today, even to "just fill the form and see." Reviewing/rejecting/editing
is still fine at any remaining count, including 0; only the real Indeed
submit step is gated by the cap.

```
GET {APP_URL}/api/auto-apply/candidates?status=ready_for_review
Header: X-Ingest-Token: {AUTO_APPLY_INGEST_TOKEN}
```

Response is wrapped in `{"data": [...]}` (standard Laravel resource
wrapping — see any existing controller in this repo for the convention).

If the list is empty: tell the human there's nothing to review right now
and stop. Do not invent or guess at candidates.

Otherwise, work through the list **one candidate at a time**, in order.
Never batch-present multiple candidates and ask for one combined decision
— each one gets its own presentation and its own explicit decision.

## Step 2 — Present one candidate

For the current candidate, show the human, in conversation:

1. **Posting summary**: company, role, location, work_mode, salary range
   (`salary_min`–`salary_max`), hours_per_week, and `posting_url`.
2. **Match reasoning**: the candidate's `match_reasoning` field verbatim.
3. **Tailored resume vs. base resume**: read `tailored_resume_markdown_path`
   (from the API response) directly — this is the actual Markdown that was
   rendered to the PDF, not the PDF itself. Read the base variant it was
   tailored from at `{ERU_VAULT_PATH}/02-Areas/Career/Resumes/Resume-{resume_variant}.md`.
   Present a diff-style comparison (what changed, section by section) plus
   `resume_variant_reason` (why this variant was picked for this posting)
   so the human can judge the tailoring, not just re-read the whole resume.

## Step 3 — Get a decision: approve / reject / edit

Ask explicitly which of the three the human wants for this candidate.
Do not proceed on silence or an ambiguous answer — ask again.

- **Reject**:
  ```
  POST {APP_URL}/api/auto-apply/candidates/{id}/reject
  Header: X-Ingest-Token: {AUTO_APPLY_INGEST_TOKEN}
  ```
  Confirm it happened, move to the next candidate.
- **Edit**: the human describes a change to the tailored resume content.
  Edit `tailored_resume_markdown_path` directly to reflect it, then
  re-render to PDF with the same tool the backend uses:
  `proteus convert {markdown_path} --to pdf --output {tailored_resume_path}`
  (swap in the `PROTEUS_BIN` value from `.env` if it isn't just `proteus`
  on PATH). Re-present the updated resume and ask again — edit is a loop
  back to Step 3, not an automatic approve.
- **Approve**: continue to Step 4 for this candidate. Do **not** call the
  submit API yet — that only happens after a real submission actually
  succeeds (Step 7).

## Step 4 — Confirm this is actually an Indeed Easy Apply posting

Navigate to `posting_url` with claude-in-chrome and read the page.

- If the domain isn't `indeed.com`, or it's an ATS the roadmap put out of
  scope (Workday, Greenhouse, Lever, a company careers page, a job
  aggregator like bebee.com, etc.), or the page has no visible Easy Apply
  entry point: **do not attempt to force it through the flow.** Tell the
  human this posting requires manual apply, and stop for this candidate —
  leave its status as `ready_for_review` (do not reject it; it's still a
  legitimate match, it just can't go through this automated path). Move
  on to the next candidate.
- If the posting is no longer live (removed, 404, "no longer accepting
  applications"): tell the human, stop for this candidate, leave it as
  `ready_for_review` (or ask the human whether to reject it as stale — do
  not decide that yourself).
- CAPTCHAs and other anomalous page states: see "Pacing and anomaly
  handling" above — it applies here and through every step that follows.

## Step 5 — Fill the Easy Apply form

Using claude-in-chrome, open Easy Apply and fill each field from the
candidate's tailored resume / posting context, including uploading
`tailored_resume_path` via the file-upload tool.

**Known open risk, check this early**: the file-upload tool may reject
`tailored_resume_path` if it isn't a path the browser session recognizes
as "shared" with it. If it's rejected: tell the human immediately, don't
retry blindly. Fallback options, in order: (1) ask the human to manually
attach the PDF from that path when prompted, (2) ask the human whether to
copy it into a location the tool does accept. Don't guess which fallback
to use — ask.

**Any custom screening question — anything beyond the standard
name/email/resume/phone fields Indeed itself always asks — stops the
flow.** Present the exact question text to the human and ask them how to
answer it. This applies no matter how simple or obviously-answerable the
question looks ("Are you authorized to work in [location]?", a yes/no
toggle, a number field) — never guess, ever, regardless of how
low-stakes it seems. This is a hard rule, not a judgment call left to
you in the moment.

Once every field is filled and every screening question has been
answered by the human (not guessed), stop right before clicking the
final Submit button and re-read **THE ONE RULE** at the top of this file.

## Step 6 — The pre-submit confirmation gate

Re-check the cap first — time has passed since Step 1, and if this is a
later candidate in the same run, earlier submits in *this* session may
have used up remaining capacity:

```
GET {APP_URL}/api/auto-apply/candidates/cap-status
Header: X-Ingest-Token: {AUTO_APPLY_INGEST_TOKEN}
```

If `remaining` is 0: stop here, before clicking anything. Tell the human
the cap was reached during this session and this candidate's form is
filled but won't be submitted today — leave it for tomorrow rather than
submitting it for real now.

Otherwise, tell the human exactly what is about to happen (company, role,
that this is the real, final Easy Apply submission, not a preview) and
ask directly: **"Ready for me to submit this?"**

- If the human says anything other than a clear yes: stop, don't submit,
  ask what they want instead (edit something and retry, abandon this
  candidate for now, etc.).
- Only on an explicit yes: click Submit.

## Step 7 — After a real submit

Confirm the submission actually succeeded (a confirmation page/message,
not just "the click didn't error") before calling the backend. Then:

```
POST {APP_URL}/api/auto-apply/candidates/{id}/submit
Header: X-Ingest-Token: {AUTO_APPLY_INGEST_TOKEN}
```

Tell the human this candidate is now `submitted` and a matching
`JobApplication` (status `Applied`) exists in the v1 list view. Move on
to the next candidate in the queue (back to Step 2).

If the submit click appears to fail or the outcome is ambiguous: do not
call the submit API (it would create a `JobApplication` for something
that may not have actually gone through) — tell the human what you saw
and ask how to proceed.

**If the submit API itself still returns 429** at this point (it
shouldn't, in normal use — Steps 1 and 6 already checked `cap-status`
first, so this means the cap was reached by something else in the gap
between that check and this call): the real Easy Apply submission on
Indeed has *already gone through* — the cap only blocks recording it,
which is itself worth flagging to the human as a gap, not silently
absorbed. Tell the human plainly: the application was actually submitted
on Indeed, but hit today's cap and could not be recorded as a
`JobApplication` — they may want to add it manually. Then **stop the
queue for the rest of today** — do not attempt further candidates'
submits once the cap is hit, even if more remain `ready_for_review`.
Reviewing/rejecting/editing remaining candidates is
still fine; only the submit step is capped.
