<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AutoApplyCandidateStatus;
use App\Http\Requests\IndexAutoApplyCandidatesRequest;
use App\Http\Resources\AutoApplyCandidateResource;
use App\Http\Resources\JobApplicationResource;
use App\Models\AutoApplyCandidate;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * v2 Phase 5: the API surface the jat-review-queue Claude Code skill drives
 * (.claude/skills/jat-review-queue/SKILL.md) — list candidates waiting on
 * human review, reject one, or submit one (creating the real
 * JobApplication row and marking the candidate Submitted). Guarded by the
 * same auto-apply.token middleware as /auto-apply/ingest — this is a local
 * Claude Code session calling its own backend, not the browser SPA, so
 * Sanctum's cookie auth doesn't apply here either (see
 * VerifyAutoApplyIngestToken).
 *
 * Deliberately: this controller never talks to Indeed or claude-in-chrome
 * itself. submit() records that a human already approved and a real
 * submission already happened — see SKILL.md for the actual browser
 * automation and the mandatory pre-submit confirmation gate.
 */
class AutoApplyCandidateController extends Controller
{
    /**
     * Name of the mutex guarding reject()/submit() against each other.
     *
     * The skill drives this API one candidate at a time in practice, but
     * nothing stops a retried HTTP request or a second session from racing
     * a concurrent call for the same (or another) candidate — without this,
     * two near-simultaneous submit() calls could both read the daily cap as
     * "not yet reached" and both create a JobApplication, or a reject() and
     * a submit() for the same candidate could both pass the ReadyForReview
     * guard before either write lands. A single named lock around every
     * state-mutating action here closes both races without needing
     * DB-driver-specific row locking (this app runs on sqlite in dev/test).
     */
    private const SUBMIT_LOCK = 'auto-apply:candidate-mutation';

    /**
     * GET /api/auto-apply/candidates?status=ready_for_review
     *
     * status is optional — omitting it lists every candidate, newest
     * first. The skill always passes status=ready_for_review in practice,
     * but nothing here forces that (useful for a human just poking the
     * API directly too).
     */
    public function index(IndexAutoApplyCandidatesRequest $request)
    {
        $query = AutoApplyCandidate::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return AutoApplyCandidateResource::collection($query->get());
    }

    /**
     * GET /api/auto-apply/candidates/cap-status
     *
     * v2 Phase 6: lets the jat-review-queue skill check remaining daily
     * capacity *before* driving a real Indeed submission — not just
     * finding out via a 429 after the real click already happened. "Enforced
     * before any submit fires" means before the real submit, not only
     * before the record of it — this is what makes that true in practice.
     */
    public function capStatus()
    {
        return response()->json($this->capSnapshot());
    }

    /**
     * POST /api/auto-apply/candidates/{candidate}/reject
     *
     * Human rejected during review — terminal; no JobApplication is ever
     * created for this candidate. Only valid from ReadyForReview: rejecting
     * an already-submitted or already-rejected candidate is almost
     * certainly a mis-click, not a real action to allow silently.
     */
    public function reject(AutoApplyCandidate $candidate)
    {
        return $this->withCandidateLock($candidate, function (AutoApplyCandidate $candidate) {
            if ($guard = $this->ensureReadyForReview($candidate, 'nothing to reject')) {
                return $guard;
            }

            $candidate->update(['status' => AutoApplyCandidateStatus::Rejected]);

            return new AutoApplyCandidateResource($candidate);
        });
    }

    /**
     * POST /api/auto-apply/candidates/{candidate}/submit
     *
     * Called by the skill only *after* a human has explicitly confirmed,
     * in that live conversation, that the real Indeed Easy Apply
     * submission has actually gone through — this endpoint doesn't submit
     * anything itself, it just records that one did (see SKILL.md). Only
     * valid from ReadyForReview — this is the guard the roadmap's exit
     * criteria depends on ("guarded so it only works from
     * ReadyForReview") and it's what stops a double-submit from creating a
     * second JobApplication row for the same candidate.
     *
     * The status check, the cap check, and the write all happen under
     * SUBMIT_LOCK so a concurrent reject()/submit() (or two racing
     * submit()s) can't both observe the pre-write state as valid.
     */
    public function submit(AutoApplyCandidate $candidate)
    {
        return $this->withCandidateLock($candidate, function (AutoApplyCandidate $candidate) {
            if ($guard = $this->ensureReadyForReview($candidate, 'refusing to submit')) {
                return $guard;
            }

            // v2 Phase 6: the daily cap is enforced here too, not just
            // documented as a convention — belt-and-suspenders alongside the
            // skill checking GET .../cap-status before ever driving a real
            // Indeed submission. A candidate beyond the cap never becomes a
            // real JobApplication, full stop, even if the skill's own
            // pre-check was somehow skipped or stale.
            $snapshot = $this->capSnapshot();

            if ($snapshot['remaining'] <= 0) {
                return response()->json([
                    'message' => "Daily auto-apply cap reached ({$snapshot['submitted_today']}/{$snapshot['cap']} submitted today). Try again tomorrow.",
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            $jobApplication = DB::transaction(function () use ($candidate) {
                $jobApplication = JobApplication::create([
                    'company' => $candidate->company,
                    'role' => $candidate->role,
                    'status' => ApplicationStatus::Applied,
                    'salary_min' => $candidate->salary_min,
                    'salary_max' => $candidate->salary_max,
                    'posting_url' => $candidate->posting_url,
                    'posting_text' => $candidate->posting_text,
                    'location' => $candidate->location,
                    'work_mode' => $candidate->work_mode,
                    'red_flags' => [],
                    'notes' => $this->buildNotes($candidate),
                ]);

                $candidate->update(['status' => AutoApplyCandidateStatus::Submitted]);

                return $jobApplication;
            });

            return (new JobApplicationResource($jobApplication))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        });
    }

    /**
     * Shared lock-acquire-and-refresh wrapper for reject()/submit() — both
     * need the exact same SUBMIT_LOCK + refresh() sequence before doing
     * their own (different) status/cap checks and writes, so that sequence
     * lives in one place rather than being pasted into each method.
     *
     * @param  callable(AutoApplyCandidate): mixed  $callback
     */
    private function withCandidateLock(AutoApplyCandidate $candidate, callable $callback): mixed
    {
        return Cache::lock(self::SUBMIT_LOCK, 10)->block(5, function () use ($candidate, $callback) {
            $candidate->refresh();

            return $callback($candidate);
        });
    }

    /**
     * Shared "wrong status" 422 guard for reject()/submit() — both only
     * operate on a ReadyForReview candidate, and both need the same
     * candidate-id/current-status message shape, just with a different
     * trailing clause describing what was refused.
     */
    private function ensureReadyForReview(AutoApplyCandidate $candidate, string $refusalReason): ?JsonResponse
    {
        if ($candidate->status !== AutoApplyCandidateStatus::ReadyForReview) {
            return response()->json([
                'message' => "Candidate #{$candidate->id} is '{$candidate->status->value}', not 'ready_for_review' — {$refusalReason}.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * The cap + today's usage together, in the one shape both capStatus()
     * and submit() need — a single source of truth so the number the skill
     * is shown via GET .../cap-status can never drift from the threshold
     * submit() actually enforces.
     *
     * @return array{cap: int, submitted_today: int, remaining: int}
     */
    private function capSnapshot(): array
    {
        $cap = (int) config('services.auto_apply.daily_cap');
        $submittedToday = $this->submittedToday();

        return [
            'cap' => $cap,
            'submitted_today' => $submittedToday,
            'remaining' => max(0, $cap - $submittedToday),
        ];
    }

    /**
     * "Submitted today" — read off Submitted candidates' updated_at, since
     * status flip is the only thing that ever touches a candidate after
     * it's Submitted (terminal), so no separate submitted_at column is
     * needed.
     *
     * "Today" means the current calendar day in Asia/Manila (matching
     * n8n's own schedule), computed here explicitly rather than by
     * overriding the app-wide timezone — every stored timestamp in this
     * app (including pre-existing JobApplication rows) is UTC, and this
     * only converts the day boundary, not the stored values themselves.
     */
    private function submittedToday(): int
    {
        $startOfDayManila = now('Asia/Manila')->startOfDay()->utc();
        $endOfDayManila = now('Asia/Manila')->endOfDay()->utc();

        return AutoApplyCandidate::where('status', AutoApplyCandidateStatus::Submitted)
            ->whereBetween('updated_at', [$startOfDayManila, $endOfDayManila])
            ->count();
    }

    /**
     * Everything v2-specific that job_applications has no column for
     * (resume_variant, match_reasoning, tailored_resume_path, ...) —
     * preserved as free text rather than adding those columns to v1's
     * schema (JAT-Roadmap-AutoApply.md Phase 1's "zero migration risk"
     * rationale, reaffirmed for Phase 5).
     */
    private function buildNotes(AutoApplyCandidate $candidate): string
    {
        $lines = [
            "Submitted via the jat-review-queue Claude Code skill (auto-apply candidate #{$candidate->id}).",
        ];

        if ($candidate->resume_variant) {
            $reason = $candidate->resume_variant_reason ? " — {$candidate->resume_variant_reason}" : '';
            $lines[] = "Resume variant: {$candidate->resume_variant}{$reason}";
        }

        if ($candidate->match_reasoning) {
            $lines[] = "Match reasoning: {$candidate->match_reasoning}";
        }

        // Strict null check, not truthiness: 0 is a real, storable
        // hours_per_week value (unsignedInteger, nullable), not the same as
        // "not set" — a falsy-zero check here would silently drop it.
        if ($candidate->hours_per_week !== null) {
            $lines[] = "Hours/week: {$candidate->hours_per_week}";
        }

        if ($candidate->tailored_resume_path) {
            $lines[] = "Tailored resume: {$candidate->tailored_resume_path}";
        }

        return implode("\n\n", $lines);
    }
}
