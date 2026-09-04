<?php

namespace App\Console\Commands;

use App\Models\JobSearchCriteria;
use App\Services\AutoApplyIngestService;
use App\Services\JobSearchClient;
use Illuminate\Console\Command;

/**
 * Dev/testing entry point for Phase 2 — calls JSearch directly via
 * JobSearchClient, then feeds the raw results through the exact same
 * AutoApplyIngestService that POST /api/auto-apply/ingest uses, so Phase 2
 * can be exercised end-to-end before n8n (Phase 4) exists.
 */
class AutoApplySearchCommand extends Command
{
    protected $signature = 'auto-apply:search';

    protected $description = 'Search JSearch using the active job_search_criteria row and ingest matching postings';

    public function handle(JobSearchClient $client, AutoApplyIngestService $ingestService): int
    {
        $criteria = JobSearchCriteria::first();

        if ($criteria === null) {
            $this->error('No job_search_criteria row exists yet — create one first.');

            return self::FAILURE;
        }

        $this->info('Searching JSearch...');
        $postings = $client->search($criteria);
        $this->info(sprintf('JSearch returned %d posting(s).', count($postings)));

        $summary = $ingestService->ingest($postings);

        $this->table(
            ['Received', 'Matched', 'Skipped (duplicate)', 'Skipped (non-match)'],
            [[$summary['received'], $summary['matched'], $summary['skipped_duplicate'], $summary['skipped_non_match']]],
        );

        return self::SUCCESS;
    }
}
