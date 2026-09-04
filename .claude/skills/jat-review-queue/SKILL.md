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

## Prerequisites

- Laravel API running locally (`php artisan serve`, default `http://localhost:8000`).
- `AUTO_APPLY_INGEST_TOKEN` readable from this repo's `.env` — send it as
  the `X-Ingest-Token` header on every API call below.
- `claude-in-chrome` connected, with the human already logged into Indeed
  in that browser session (this skill does not log in on their behalf).
- `ERU_VAULT_PATH` readable from `.env`, for reading each resume variant's
  base source Markdown (`{ERU_VAULT_PATH}/02-Areas/Career/Resumes/Resume-{variant}.md`).

## Step 1 — Fetch the queue

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
- If a CAPTCHA or another hard verification challenge appears at any
  point in this flow: **stop immediately and report it to the human.**
  Do not attempt to solve it, wait it out, retry, or route around it in
  any way — this is explicitly out of scope
  (JAT-Roadmap-AutoApply.md, Decided Against). This applies even if it
  appears mid-form, not just on initial navigation.

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

Tell the human exactly what is about to happen (company, role, that this
is the real, final Easy Apply submission, not a preview) and ask directly:
**"Ready for me to submit this?"**

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
