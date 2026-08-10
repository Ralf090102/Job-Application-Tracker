<?php

namespace App\Contracts;

/**
 * Contract for "raw job posting text in, structured fields out." Kept as an
 * interface (not a call to OllamaJobPostingExtractor directly) so tests can
 * bind a fake implementation instead of hitting a real LLM — same reasoning
 * as any other external-service boundary in Laravel.
 */
interface JobPostingExtractor
{
    /**
     * @return array{
     *     company: ?string,
     *     role: ?string,
     *     salary_min: ?int,
     *     salary_max: ?int,
     *     location: ?string,
     *     work_mode: ?string,
     *     red_flags: array<int, string>,
     * }
     *
     * @throws \App\Exceptions\JobPostingExtractionException
     */
    public function extract(string $postingText): array;
}
