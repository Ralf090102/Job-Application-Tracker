<?php

namespace App\Services;

use App\Contracts\ResumeTailor;
use App\Exceptions\OllamaException;
use App\Exceptions\ResumeTailoringException;
use Illuminate\Support\Facades\Log;

/**
 * Calls the same local Ollama server as the other v2 LLM services for
 * Phase 3's two decisions: which resume variant fits a posting, and the
 * actual tailored content. Two separate calls, not one — selecting a
 * variant only needs each resume's short framing summary, not its full
 * raw content, so that call stays small/cheap; only the heavier `tailor()`
 * call needs the full resume + portfolio text and a larger context window
 * (JAT-Roadmap-AutoApply.md Phase 3).
 */
class OllamaResumeTailor implements ResumeTailor
{
    private const SELECT_RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'variant' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['variant', 'reason'],
    ];

    public function __construct(private OllamaClient $client) {}

    public function selectVariant(string $role, string $company, string $postingText, array $variantSummaries): array
    {
        $variantKeys = array_keys($variantSummaries);

        $summaryLines = implode("\n", array_map(
            fn ($key) => "- {$key}: {$variantSummaries[$key]}",
            $variantKeys,
        ));

        $systemPrompt = <<<PROMPT
            You choose which of several resume variants best fits a job posting.
            Each variant is summarized below by its own stated target audience.
            Respond with JSON only, matching the given schema exactly. "variant"
            must be exactly one of: {$this->quotedList($variantKeys)}.

            Resume variants:
            {$summaryLines}
            PROMPT;

        $userMessage = "Company: {$company}\nRole: {$role}\n\nPosting:\n{$postingText}";

        try {
            $decoded = $this->client->chatJson($systemPrompt, $userMessage, self::SELECT_RESPONSE_SCHEMA, contextSize: 4096, timeoutSeconds: 120);
        } catch (OllamaException $e) {
            throw new ResumeTailoringException($e->getMessage(), previous: $e);
        }

        $variant = is_string($decoded['variant'] ?? null) ? $decoded['variant'] : null;

        if (! in_array($variant, $variantKeys, true)) {
            // Malformed/out-of-range pick — fall back to the first variant
            // rather than failing the whole candidate over this one call.
            Log::warning('Resume tailoring: model picked an invalid variant', ['raw' => $decoded['variant'] ?? null]);
            $variant = $variantKeys[0];
        }

        return [
            'variant' => $variant,
            'reason' => is_string($decoded['reason'] ?? null) ? $decoded['reason'] : '',
        ];
    }

    public function tailor(
        string $role,
        string $company,
        string $postingText,
        string $resumeMarkdown,
        string $portfolioMarkdown,
    ): string {
        $systemPrompt = <<<PROMPT
            You produce a tailored one-page resume in Markdown for a specific job
            posting, using ONLY real, truthful content from the two sources below
            — never invent anything.

            SOURCE 1 — the candidate's existing resume note for this target
            audience. This is a personal PLANNING document, not a finished
            resume: it contains meta-commentary ("## Status", "## Goal",
            "## Open questions / TODO", "## Related", etc.) that you must IGNORE
            entirely. It also explicitly annotates what was actually shipped vs.
            cut — for example "cut from the final 1-page PDF" or "kept here for
            reference only". Use ONLY bullets/content that are current and
            shipped. Never use anything explicitly marked cut, dropped,
            deferred, or superseded.

            SOURCE 1:
            {$resumeMarkdown}

            SOURCE 2 — the full project portfolio, a clean reference with more
            detail than fits a 1-page resume. Use this ONLY to restore a
            trimmed-for-space detail from SOURCE 1 that fits this specific
            posting better. Never introduce a fact that isn't in SOURCE 1 or
            SOURCE 2.

            SOURCE 2:
            {$portfolioMarkdown}

            TARGET POSTING:
            Company: {$company}
            Role: {$role}
            Description: {$postingText}

            Output ONLY the resume itself, as Markdown, with this structure:

            # <name from SOURCE 2's Contact section>
            <one line: email · github · linkedin · phone, all from SOURCE 2>

            ## Experience
            (entries pulled from SOURCE 1's Experience section — keep the ones
            still current per SOURCE 1's own annotations)

            ## Projects
            (2-4 entries most relevant to this posting, from SOURCE 1's Projects
            section — reorder/select for relevance, don't invent new ones)

            Do NOT write a Skills section — that is appended separately,
            verbatim from the source, after your output. Stop after Projects.

            Rules:
            - Every fact must be traceable to SOURCE 1 or SOURCE 2 — no invented
              metrics, dates, or technologies. This applies even when the
              target posting mentions a technology the candidate doesn't have
              — do NOT add it just because the posting asks for it.
            - No meta-commentary, no "Status"/"TODO" headers, no explanation of
              your choices — output ONLY the resume content itself.
            - Nothing SOURCE 1 marks as cut, dropped, or deferred.
            - Prefer trimming over padding — this must plausibly fit one page.
            PROMPT;

        // A ~16k-token prompt (full resume note + full portfolio + posting)
        // on CPU-bound local inference measured well past 180s in testing —
        // generous timeouts on both fronts, mirroring the same "found a
        // real wall live, raised both ceilings" pattern as v1's Ollama
        // extractor (see OllamaJobPostingExtractor). No JSON schema here —
        // the output is free-form Markdown.
        try {
            return $this->client->chatText($systemPrompt, 'Produce the tailored resume now.', contextSize: 16384, timeoutSeconds: 600);
        } catch (OllamaException $e) {
            throw new ResumeTailoringException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function quotedList(array $keys): string
    {
        return implode(', ', array_map(fn ($k) => "\"{$k}\"", $keys));
    }
}
