<?php

namespace App\Services;

use App\Contracts\JobPostingMatcher;
use App\Enums\WorkMode;
use App\Exceptions\JobPostingMatchException;
use App\Models\AutoApplyCandidate;
use App\Models\JobSearchCriteria;
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

        foreach ($rawPostings as $raw) {
            $posting = $this->normalize($raw);

            if ($posting === null) {
                $summary['skipped_non_match']++;

                continue;
            }

            if (AutoApplyCandidate::where('posting_url', $posting['posting_url'])->exists()) {
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

            $candidate = AutoApplyCandidate::create([
                ...$posting,
                'match_reasoning' => $reasoning,
            ]);

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
        $postingUrl = $raw['job_apply_link'] ?? $raw['job_google_link'] ?? null;
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

        if ($criteria->work_mode !== null && $posting['work_mode'] !== $criteria->work_mode->value) {
            return false;
        }

        // hours_per_week isn't populated from JSearch yet (see normalize()),
        // so there's nothing to bound-check against hours_min/hours_max
        // until a posting actually supplies it.

        return true;
    }
}
