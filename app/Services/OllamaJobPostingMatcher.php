<?php

namespace App\Services;

use App\Contracts\JobPostingMatcher;
use App\Exceptions\JobPostingMatchException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function evaluate(string $postingText, string $avoidIfRubric): array
    {
        $url = rtrim(config('services.ollama.url'), '/');
        $model = config('services.ollama.model');

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

        // See OllamaJobPostingExtractor for why this is needed alongside
        // Http::timeout() — PHP's own max_execution_time is a separate,
        // lower ceiling.
        set_time_limit(150);

        try {
            $response = Http::timeout(120)->post("{$url}/api/chat", [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $postingText],
                ],
                'format' => self::RESPONSE_SCHEMA,
            ]);
        } catch (Throwable $e) {
            throw new JobPostingMatchException(
                "Couldn't reach Ollama at {$url} — is it running? (`ollama serve`)",
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new JobPostingMatchException(
                "Ollama returned an error (HTTP {$response->status()}): ".$response->body(),
            );
        }

        $raw = $response->json('message.content');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            Log::warning('Job posting match: model did not return valid JSON', ['raw' => $raw]);
            throw new JobPostingMatchException(
                'The model returned something that was not valid JSON.',
            );
        }

        return [
            // Malformed/missing 'matches' defaults to true — see class doc.
            'matches' => is_bool($decoded['matches'] ?? null) ? $decoded['matches'] : true,
            'reasoning' => is_string($decoded['reasoning'] ?? null) ? $decoded['reasoning'] : '',
        ];
    }
}
