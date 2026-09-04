<?php

namespace App\Services;

use App\Contracts\JobPostingMatcher;
use App\Exceptions\JobPostingMatchException;
use App\Exceptions\OllamaException;

/**
 * Calls the same local Ollama server as OllamaJobPostingExtractor (v1
 * Phase 5) to run the free-text "avoid if..." rubric from
 * job_search_criteria against a candidate posting — the LLM half of Phase
 * 2's deterministic + LLM matching pass (JAT-Roadmap-AutoApply.md Phase 2).
 * Deliberately conservative: a malformed model response is treated as a
 * pass (matches = true) rather than silently filtering a posting out.
 */
class OllamaJobPostingMatcher implements JobPostingMatcher
{
    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'matches' => ['type' => 'boolean'],
            'reasoning' => ['type' => 'string'],
        ],
        'required' => ['matches', 'reasoning'],
    ];

    public function __construct(private OllamaClient $client) {}

    public function evaluate(string $postingText, string $avoidIfRubric): array
    {
        $systemPrompt = <<<PROMPT
            You decide whether a job posting should be filtered out based on a
            free-text rubric of things to avoid. "matches" means the posting is
            ACCEPTABLE (does NOT trip the rubric) — true lets it through, false
            filters it out. Be conservative: if the rubric doesn't clearly
            apply to this posting, matches should be true. Always give a short
            one-sentence reasoning explaining the verdict.

            Rubric (things to avoid):
            {$avoidIfRubric}

            Respond with JSON only, matching the given schema exactly.
            PROMPT;

        try {
            $decoded = $this->client->chatJson($systemPrompt, $postingText, self::RESPONSE_SCHEMA, timeoutSeconds: 120);
        } catch (OllamaException $e) {
            throw new JobPostingMatchException($e->getMessage(), previous: $e);
        }

        return [
            // Malformed/missing 'matches' defaults to true — see class doc.
            'matches' => is_bool($decoded['matches'] ?? null) ? $decoded['matches'] : true,
            'reasoning' => is_string($decoded['reasoning'] ?? null) ? $decoded['reasoning'] : '',
        ];
    }
}
