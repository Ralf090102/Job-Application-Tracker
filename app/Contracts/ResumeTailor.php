<?php

namespace App\Contracts;

/**
 * Contract for the two LLM decisions Phase 3 needs: which of the three
 * resume variants fits a posting, and the actual tailored content. Split
 * into two methods (rather than one big call) so each gets a right-sized
 * prompt/context — selecting a variant only needs each resume's own
 * one-line "who this is for" framing, not its full messy raw content.
 */
interface ResumeTailor
{
    /**
     * @param  array<string, string>  $variantSummaries  variant key => short "who this is for" summary
     * @return array{variant: string, reason: string}
     *
     * @throws \App\Exceptions\ResumeTailoringException
     */
    public function selectVariant(string $role, string $company, string $postingText, array $variantSummaries): array;

    /**
     * @return string tailored resume, as Markdown
     *
     * @throws \App\Exceptions\ResumeTailoringException
     */
    public function tailor(
        string $role,
        string $company,
        string $postingText,
        string $resumeMarkdown,
        string $portfolioMarkdown,
    ): string;
}
