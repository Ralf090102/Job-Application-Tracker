<?php

namespace App\Contracts;

/**
 * Contract for "posting text + a free-text negative-flag rubric in, a
 * match verdict + reasoning out." Same shape as JobPostingExtractor — an
 * interface so tests can bind a fake instead of hitting a real LLM.
 */
interface JobPostingMatcher
{
    /**
     * @return array{matches: bool, reasoning: string}
     *
     * @throws \App\Exceptions\JobPostingMatchException
     */
    public function evaluate(string $postingText, string $avoidIfRubric): array;
}
