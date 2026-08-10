<?php

namespace App\Http\Controllers;

use App\Contracts\JobPostingExtractor;
use App\Exceptions\JobPostingExtractionException;
use App\Http\Requests\ExtractJobPostingRequest;
use Illuminate\Http\Response;

class JobPostingExtractionController extends Controller
{
    /**
     * Extract structured fields (+ red flags) from raw job-posting text.
     * Does NOT persist anything — Phase 3's real create endpoint handles
     * that, once the user has reviewed/edited what came back here.
     */
    public function store(ExtractJobPostingRequest $request, JobPostingExtractor $extractor)
    {
        try {
            $extracted = $extractor->extract($request->validated('posting_text'));
        } catch (JobPostingExtractionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json(['data' => $extracted]);
    }
}
