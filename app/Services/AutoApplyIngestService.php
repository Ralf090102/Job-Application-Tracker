<?php

namespace App\Services;

use App\Contracts\JobPostingMatcher;
use App\Enums\AutoApplyCandidateStatus;
use App\Enums\WorkMode;
use App\Exceptions\JobPostingMatchException;
use App\Jobs\TailorResumeCandidate;
use App\Models\AutoApplyCandidate;
use App\Models\JobSearchCriteria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Turns raw JSearch posting objects into AutoApplyCandidate rows. Shared by
 * POST /api/auto-apply/ingest (production: n8n's cron posts here) and
 * `php artisan auto-apply:search` (dev/testing) so both entry points run
 * the exact same matching logic (JAT-Roadmap-AutoApply.md Phase 2).
 *
 * Per the roadmap, duplicates and non-matches are simply not stored — only
 * a posting that passes both the deterministic bounds check and the LLM
 * rubric pass becomes a real `matched` row. Callers get a summary back to
 * log/display rather than a persisted record of what got filtered out.
 */
class AutoApplyIngestService
{
    public function __construct(private JobPostingMatcher $matcher) {}

    /**
     * @param  array<int, array<string, mixed>>  $rawPostings  raw JSearch posting objects
     * @return array{received: int, matched: int, skipped_duplicate: int, skipped_non_match: int, candidates: array<int, AutoApplyCandidate>}
     */
    public function ingest(array $rawPostings): array
    {
        $criteria = JobSearchCriteria::first();

        $summary = [
            'received' => count($rawPostings),
            'matched' => 0,
            'skipped_duplicate' => 0,
            'skipped_non_match' => 0,
            'candidates' => [],
        ];

        // Normalize every posting up front so duplicate-checking can be one
        // batched query instead of one exists() query per posting inside
        // the loop (found via /bug-sweep 2026-09-04 — directly relevant to
        // how many postings fit before the request itself times out).
        $normalized = [];

        foreach ($rawPostings as $raw) {
            $posting = $this->normalize($raw);

            if ($posting === null) {
                $summary['skipped_non_match']++;

                continue;
            }

            $normalized[] = $posting;
        }

        $existingUrls = $normalized === []
            ? collect()
            : AutoApplyCandidate::whereIn('posting_url', array_column($normalized, 'posting_url'))
                ->pluck('posting_url')
                ->flip();

        foreach ($normalized as $posting) {
            if ($existingUrls->has($posting['posting_url'])) {
                $summary['skipped_duplicate']++;

                continue;
            }

            if (! $this->passesDeterministicMatch($posting, $criteria)) {
                $summary['skipped_non_match']++;

                continue;
            }

            $reasoning = 'No rubric configured — accepted by default.';

            if ($criteria?->avoid_if_rubric) {
                try {
                    $result = $this->matcher->evaluate($posting['posting_text'], $criteria->avoid_if_rubric);
                } catch (JobPostingMatchException $e) {
                    // A single bad match call shouldn't abort the whole
                    // batch — log it and skip just this posting.
                    Log::warning('Auto-apply ingest: matcher call failed', ['error' => $e->getMessage()]);
                    $summary['skipped_non_match']++;

                    continue;
                }

                if (! $result['matches']) {
                    $summary['skipped_non_match']++;

                    continue;
                }

                $reasoning = $result['reasoning'];
            }

            try {
                $candidate = AutoApplyCandidate::create([
                    ...$posting,
                    // The migration's column default is 'discovered' — a
                    // posting that made it here already passed both the
                    // deterministic and LLM matching passes, so it belongs
                    // at 'matched', not the default (JAT-Roadmap-AutoApply.md
                    // Phase 2: "matches move to matched").
                    'status' => AutoApplyCandidateStatus::Matched,
                    'match_reasoning' => $reasoning,
                ]);
            } catch (QueryException $e) {
                // TOCTOU: another concurrent ingest call (e.g. an n8n retry
                // firing mid-batch) inserted this exact posting_url between
                // the batched dedup check above and this create() — treat
                // it as a duplicate instead of letting the unique-constraint
                // violation 500 the rest of this batch (/bug-sweep
                // 2026-09-04).
                Log::warning('Auto-apply ingest: posting_url race on create()', ['posting_url' => $posting['posting_url']]);
                $summary['skipped_duplicate']++;

                continue;
            }

            // Phase 3: tailor + render, queued rather than run inline —
            // a single candidate's tailoring can take up to ~600-750s, far
            // past what an HTTP request/reverse proxy would tolerate for a
            // multi-candidate batch (/bug-sweep 2026-09-04). Requires a
            // queue worker running; QUEUE_CONNECTION=sync in tests runs
            // this inline with no extra setup.
            TailorResumeCandidate::dispatch($candidate);

            $summary['matched']++;
            $summary['candidates'][] = $candidate;
        }

        return $summary;
    }

    /**
     * Maps a raw JSearch posting object to this app's field names. Returns
     * null for a posting missing the fields needed to identify it at all.
     *
     * Defensive by design — JSearch's exact field names should be
     * double-checked against a live response (Phase 2's exit criteria);
     * this is the one place to adjust if any don't match.
     *
     * @return array{posting_url: string, company: string, role: string, salary_min: ?int, salary_max: ?int, hours_per_week: ?int, location: ?string, work_mode: string, posting_text: string}|null
     */
    private function normalize(array $raw): ?array
    {
        // job_apply_link ?? job_google_link only falls through on null, not
        // on an empty string — a posting with job_apply_link === "" (only
        // indexed via Google's job board, say) used to get discarded
        // outright instead of falling back (/bug-sweep 2026-09-04).
        $postingUrl = $raw['job_apply_link'] ?? null;

        if (! is_string($postingUrl) || $postingUrl === '') {
            $postingUrl = $raw['job_google_link'] ?? null;
        }

        $company = $raw['employer_name'] ?? null;
        $role = $raw['job_title'] ?? null;

        if (! is_string($postingUrl) || $postingUrl === ''
            || ! is_string($company) || $company === ''
            || ! is_string($role) || $role === '') {
            return null;
        }

        return [
            'posting_url' => $postingUrl,
            'company' => $company,
            'role' => $role,
            'salary_min' => is_numeric($raw['job_min_salary'] ?? null) ? (int) $raw['job_min_salary'] : null,
            'salary_max' => is_numeric($raw['job_max_salary'] ?? null) ? (int) $raw['job_max_salary'] : null,
            // JSearch doesn't reliably provide a weekly-hours figure — left
            // null until a posting actually supplies one to bound-check.
            'hours_per_week' => null,
            'location' => trim(implode(', ', array_filter([
                $raw['job_city'] ?? null,
                $raw['job_state'] ?? null,
            ]))) ?: null,
            // JSearch's job_is_remote is a plain boolean — no reliable
            // signal for Hybrid, so this only ever resolves Remote/Onsite.
            'work_mode' => ($raw['job_is_remote'] ?? false) ? WorkMode::Remote->value : WorkMode::Onsite->value,
            'posting_text' => is_string($raw['job_description'] ?? null) ? $raw['job_description'] : '',
        ];
    }

    private function passesDeterministicMatch(array $posting, ?JobSearchCriteria $criteria): bool
    {
        if ($criteria === null) {
            return true;
        }

        if ($criteria->salary_min !== null && $posting['salary_max'] !== null
            && $posting['salary_max'] < $criteria->salary_min) {
            return false;
        }

        if ($criteria->salary_max !== null && $posting['salary_min'] !== null
            && $posting['salary_min'] > $criteria->salary_max) {
            return false;
        }

        // JSearch's job_is_remote is a plain boolean (see normalize()), so
        // a posting's work_mode can only ever be Remote or Onsite — never
        // Hybrid. A criteria row set to Hybrid used to reject every single
        // posting forever, silently, since posting.work_mode could never
        // equal 'hybrid' (found via /bug-sweep 2026-09-04). Treated as "no
        // constraint" instead, since it genuinely can't be checked from
        // JSearch data.
        if ($criteria->work_mode !== null
            && $criteria->work_mode !== WorkMode::Hybrid
            && $posting['work_mode'] !== $criteria->work_mode->value) {
            return false;
        }

        // hours_per_week isn't populated from JSearch yet (see normalize()),
        // so there's nothing to bound-check against hours_min/hours_max
        // until a posting actually supplies it.

        return true;
    }
}
