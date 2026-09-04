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
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-rapidapi-host' => $host,
                    'x-rapidapi-key' => $key,
                ])
                ->get("https://{$host}/search", [
                    'query' => $query,
                    'num_pages' => 1,
                ]);
        } catch (Throwable $e) {
            throw new JobSearchException("Couldn't reach JSearch at {$host}.", previous: $e);
        }

        if ($response->failed()) {
            throw new JobSearchException(
                "JSearch returned an error (HTTP {$response->status()}): ".$response->body(),
            );
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }
}
