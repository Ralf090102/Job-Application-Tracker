<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AutoApplyCandidateStatus;
use App\Http\Resources\AutoApplyCandidateResource;
use App\Http\Resources\JobApplicationResource;
use App\Models\AutoApplyCandidate;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

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
     * GET /api/auto-apply/candidates?status=ready_for_review
     *
     * status is optional — omitting it lists every candidate, newest
     * first. The skill always passes status=ready_for_review in practice,
     * but nothing here forces that (useful for a human just poking the
     * API directly too).
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', new Enum(AutoApplyCandidateStatus::class)],
        ]);

        $query = AutoApplyCandidate::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return AutoApplyCandidateResource::collection($query->get());
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
        if ($candidate->status !== AutoApplyCandidateStatus::ReadyForReview) {
            return response()->json([
                'message' => "Candidate #{$candidate->id} is '{$candidate->status->value}', not 'ready_for_review' — nothing to reject.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $candidate->update(['status' => AutoApplyCandidateStatus::Rejected]);

        return new AutoApplyCandidateResource($candidate);
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
     */
    public function submit(AutoApplyCandidate $candidate)
    {
        if ($candidate->status !== AutoApplyCandidateStatus::ReadyForReview) {
            return response()->json([
                'message' => "Candidate #{$candidate->id} is '{$candidate->status->value}', not 'ready_for_review' — refusing to submit.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
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

        if ($candidate->hours_per_week) {
            $lines[] = "Hours/week: {$candidate->hours_per_week}";
        }

        if ($candidate->tailored_resume_path) {
            $lines[] = "Tailored resume: {$candidate->tailored_resume_path}";
        }

        return implode("\n\n", $lines);
    }
}
