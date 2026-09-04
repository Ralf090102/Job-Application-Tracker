<?php

namespace App\Services;

use App\Exceptions\JobSearchException;
use App\Models\JobSearchCriteria;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Calls JSearch (RapidAPI) to discover live postings matching a
 * JobSearchCriteria row's position_keywords + location. This is a
 * dev/testing tool, NOT the production trigger — in production, n8n's
 * cron calls JSearch directly and POSTs raw results to
 * POST /api/auto-apply/ingest (see JAT-Roadmap-AutoApply.md's Architecture
 * section). This class exists so Phase 2 can be built and its exit
 * criteria verified end-to-end — via `php artisan auto-apply:search` —
 * before n8n (Phase 4) is wired up at all.
 */
class JobSearchClient
{
    /**
     * @return array<int, array<string, mixed>> raw JSearch posting objects,
     *   the same shape n8n will POST to the ingest endpoint in production.
     */
    public function search(JobSearchCriteria $criteria): array
    {
        $host = config('services.jsearch.host');
        $key = config('services.jsearch.key');

        if (! $host || ! $key) {
            // Distinct from the generic "unreachable"/HTTP-error messages
            // below — a blank JSEARCH_API_KEY (it ships empty in
            // .env.example) would otherwise send a request with an empty
            // x-rapidapi-key header and surface as a confusing wrapped
            // RapidAPI 401/403 instead of a clear config error
            // (found via /bug-sweep 2026-09-04).
            throw new JobSearchException(
                'JSEARCH_API_KEY / JSEARCH_API_HOST are not configured — check your .env.',
            );
        }

        $query = trim(implode(' ', array_filter([
            implode(' ', $criteria->position_keywords ?? []),
            $criteria->location ? "in {$criteria->location}" : null,
        ])));

        if ($query === '') {
            throw new JobSearchException(
                'job_search_criteria has no position_keywords or location to search with.',
            );
        }

        try {
            // /search-v2, not /search — confirmed live against this
            // project's actual RapidAPI subscription (/search 404s with
            // "Endpoint '/search' does not exist" on this key/plan).
            // country/date_posted are required params on this endpoint.
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-rapidapi-host' => $host,
                    'x-rapidapi-key' => $key,
                ])
                ->get("https://{$host}/search-v2", [
                    'query' => $query,
                    'num_pages' => 1,
                    'country' => config('services.jsearch.country'),
                    'date_posted' => 'all',
                ]);
        } catch (Throwable $e) {
            throw new JobSearchException("Couldn't reach JSearch at {$host}.", previous: $e);
        }

        if ($response->failed()) {
            throw new JobSearchException(
                "JSearch returned an error (HTTP {$response->status()}): ".$response->body(),
            );
        }

        // Confirmed live: postings live under data.jobs, not data directly.
        $jobs = $response->json('data.jobs');

        return is_array($jobs) ? $jobs : [];
    }
}
