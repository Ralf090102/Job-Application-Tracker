<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutoApplyIngestRequest;
use App\Services\AutoApplyIngestService;

class AutoApplyIngestController extends Controller
{
    /**
     * n8n's cron hits this after calling JSearch directly (see the
     * Architecture section of JAT-Roadmap-AutoApply.md) — raw JSearch
     * posting objects in, a summary of what got matched/skipped out.
     * Nothing here calls JSearch itself; see JobSearchClient for the
     * dev/testing path that does.
     */
    public function store(StoreAutoApplyIngestRequest $request, AutoApplyIngestService $service)
    {
        $summary = $service->ingest($request->validated('postings'));

        return response()->json([
            'received' => $summary['received'],
            'matched' => $summary['matched'],
            'skipped_duplicate' => $summary['skipped_duplicate'],
            'skipped_non_match' => $summary['skipped_non_match'],
        ]);
    }
}
