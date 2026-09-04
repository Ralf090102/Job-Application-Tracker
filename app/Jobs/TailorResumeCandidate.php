<?php

namespace App\Jobs;

use App\Models\AutoApplyCandidate;
use App\Services\ResumeTailoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs ResumeTailoringService::process() off the request. Previously this
 * ran synchronously inside POST /api/auto-apply/ingest — a single
 * candidate's tailoring can take up to ~600-750s (Ollama) plus PDF render
 * time, so a batch of even a few matched postings could block the HTTP
 * response for 20-40+ minutes, well past any timeout n8n's HTTP node,
 * PHP-FPM, or a reverse proxy would tolerate (found via /bug-sweep
 * 2026-09-04). Dispatched per matched candidate from
 * AutoApplyIngestService; requires a queue worker running
 * (`php artisan queue:work`) — QUEUE_CONNECTION=database in .env, =sync in
 * tests, so the test suite still runs this inline with no extra setup.
 */
class TailorResumeCandidate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Worker-enforced ceiling, not Ollama's own per-call timeout (that's
     * still OllamaResumeTailor's job) — generous enough to cover
     * selectVariant() + tailor() + the Proteus render in one job run.
     */
    public int $timeout = 900;

    public function __construct(public AutoApplyCandidate $candidate) {}

    public function handle(ResumeTailoringService $service): void
    {
        $service->process($this->candidate);
    }
}
