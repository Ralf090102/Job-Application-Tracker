<?php

namespace App\Services;

use App\Contracts\JobPostingExtractor;
use App\Exceptions\JobPostingExtractionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls a local Ollama server (no API key, no per-request cost — see
 * Roadmap.md Phase 5's "Model choice" note) to turn raw job-posting text
 * into structured fields, using Ollama's `format` parameter (a JSON Schema)
 * to constrain the output shape.
 *
 * The system prompt embeds the actual red-flag rubric from Eru's
 * Vetting-Employers note (Section 3, "Job Posting Red Flags" — the only
 * part of that note detectable from posting text alone; SEC checks,
 * interview questions, and contract clauses aren't). It was iterated
 * against a real local run of the configured model until it stopped
 * false-positiving on ordinary multi-technology stacks — see the prompt's
 * own worked examples for the exact distinction it's meant to draw
 * (broad *job-function* scope vs. a normal *technology* list).
 */
class OllamaJobPostingExtractor implements JobPostingExtractor
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You extract structured data from a raw job posting and flag genuine red flags. Be conservative: most job postings are normal. Only report a red flag if it clearly matches one of these four patterns:

1. Overly broad role scope — the posting asks for several unrelated JOB FUNCTIONS mashed into one role (e.g. "senior engineer AND marketing manager AND sales rep"), a sign the scope was never defined. Listing several TECHNOLOGIES within the same job function (e.g. "PHP, Laravel, React" for one developer role, or "AWS, Docker, Kubernetes" for one DevOps role) is completely normal and must NEVER be flagged — that is an ordinary tech stack, not broad scope. Only flag this pattern when the role spans different departments/professions, not different tools within one profession.
2. Urgency or pressure language — phrases like "immediate hiring", "same-day offers", "apply now or miss out". Ordinary phrases like "urgently hiring" for one clearly-scoped role are borderline; only flag if combined with pressure to decide fast.
3. Pay that is suspiciously high for the stated seniority, OR suspiciously vague with literally no range given (e.g. only "competitive salary" and nothing else). A posting that simply omits salary is common and NOT automatically a flag by itself — only flag if the vagueness is paired with vague/urgent language elsewhere too.
4. Do NOT flag graveyard or odd-hour shifts by themselves — normal and expected for Philippines-based offshore/BPO roles working US time zones. Never list this as a red flag on its own.

Do not flag: a normal technical skill list for one job function, remote/hybrid/onsite mentions, standard recruiting phrasing, or missing fields in general. Zero red flags is the common, correct answer for most postings — do not force one just to have something to report.

Each red flag you do report must be a short plain-English description of the actual concern, not a copy of the raw posting text.

Respond with JSON only, matching the given schema exactly. Use null for any field not stated in the posting.

Example 1 (normal posting, single job function, multiple technologies — zero flags):
Posting: "Backend Developer needed at Clearline Systems. PHP/Laravel, 3+ years experience. Salary: PHP 45,000-60,000/month. Hybrid, Makati office. Standard business hours."
Correct output: {"company":"Clearline Systems","role":"Backend Developer","salary_min":45000,"salary_max":60000,"location":"Makati","work_mode":"hybrid","red_flags":[]}

Example 2 (one job function, several technologies — still zero flags, this is NOT broad scope):
Posting: "Full Stack Developer at Nexbridge. PHP, Laravel, React, MySQL. Salary PHP 50,000-70,000. Hybrid, Taguig."
Correct output: {"company":"Nexbridge","role":"Full Stack Developer","salary_min":50000,"salary_max":70000,"location":"Taguig","work_mode":"hybrid","red_flags":[]}

Example 3 (genuinely broad scope — different job functions mashed together):
Posting: "We need someone who can be our Senior Engineer, run Marketing campaigns, and close Sales deals. Competitive salary!"
Correct output: {"company":null,"role":"Senior Engineer / Marketing / Sales","salary_min":null,"salary_max":null,"location":null,"work_mode":null,"red_flags":["Posting combines engineering, marketing, and sales into one role with no clear scope","Salary described only as \"competitive\" with no range given"]}
PROMPT;

    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'company' => ['type' => ['string', 'null']],
            'role' => ['type' => ['string', 'null']],
            'salary_min' => ['type' => ['integer', 'null']],
            'salary_max' => ['type' => ['integer', 'null']],
            'location' => ['type' => ['string', 'null']],
            'work_mode' => ['type' => ['string', 'null'], 'enum' => ['onsite', 'remote', 'hybrid', null]],
            'red_flags' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['company', 'role', 'salary_min', 'salary_max', 'location', 'work_mode', 'red_flags'],
    ];

    public function extract(string $postingText): array
    {
        $url = rtrim(config('services.ollama.url'), '/');
        $model = config('services.ollama.model');

        // PHP's own max_execution_time (default 30s) is a SEPARATE, lower
        // ceiling than the Http::timeout() below — it kills the whole
        // process regardless of what Guzzle's timeout is set to. Found by
        // an actual live test timing out at exactly 30s despite a 120s
        // client timeout; this call is what fixes it, not that one alone.
        set_time_limit(150);

        try {
            // Local CPU inference on a 7B model ran 20-50s in testing —
            // generous timeout, well past Laravel's 30s default.
            $response = Http::timeout(120)->post("{$url}/api/chat", [
                'model' => $model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $postingText],
                ],
                'format' => self::RESPONSE_SCHEMA,
            ]);
        } catch (Throwable $e) {
            throw new JobPostingExtractionException(
                "Couldn't reach Ollama at {$url} — is it running? (`ollama serve`)",
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new JobPostingExtractionException(
                "Ollama returned an error (HTTP {$response->status()}): ".$response->body(),
            );
        }

        $raw = $response->json('message.content');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            Log::warning('Job posting extraction: model did not return valid JSON', ['raw' => $raw]);
            throw new JobPostingExtractionException(
                'The model returned something that was not valid JSON. Try again, or fill the form in by hand.',
            );
        }

        return [
            'company' => $decoded['company'] ?? null,
            'role' => $decoded['role'] ?? null,
            'salary_min' => is_int($decoded['salary_min'] ?? null) ? $decoded['salary_min'] : null,
            'salary_max' => is_int($decoded['salary_max'] ?? null) ? $decoded['salary_max'] : null,
            'location' => $decoded['location'] ?? null,
            // Normalize defensively — a small local model occasionally
            // drifts from the schema's enum despite the `format` constraint.
            'work_mode' => in_array($decoded['work_mode'] ?? null, ['onsite', 'remote', 'hybrid'], true)
                ? $decoded['work_mode']
                : null,
            'red_flags' => array_values(array_filter(
                is_array($decoded['red_flags'] ?? null) ? $decoded['red_flags'] : [],
                fn ($flag) => is_string($flag) && trim($flag) !== '',
            )),
        ];
    }
}
