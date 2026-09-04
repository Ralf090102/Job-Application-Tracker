<?php

namespace App\Services;

use App\Exceptions\OllamaException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared HTTP-call/timeout/JSON-decode plumbing for every Ollama-backed
 * service in this project. Previously copy-pasted independently across
 * OllamaJobPostingExtractor, OllamaJobPostingMatcher, and
 * OllamaResumeTailor (three-to-four near-identical copies, already
 * drifting slightly out of sync — different timeouts, inconsistent error
 * wording — found via /bug-sweep 2026-09-04). Each caller still throws its
 * own documented exception type by catching OllamaException and rewrapping
 * it, so this refactor doesn't change any public contract.
 */
class OllamaClient
{
    /**
     * A structured-output call: the response is validated JSON matching
     * the given schema (Ollama's `format` parameter).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws OllamaException
     */
    public function chatJson(
        string $systemPrompt,
        string $userMessage,
        array $schema,
        int $contextSize = 4096,
        int $timeoutSeconds = 120,
    ): array {
        $raw = $this->chat($systemPrompt, $userMessage, $timeoutSeconds, [
            'format' => $schema,
            'options' => ['num_ctx' => $contextSize],
        ]);

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            Log::warning('Ollama call: model did not return valid JSON', ['raw' => $raw]);
            throw new OllamaException('The model returned something that was not valid JSON.');
        }

        return $decoded;
    }

    /**
     * A free-form call: the response is returned as plain text, no schema.
     *
     * @throws OllamaException
     */
    public function chatText(
        string $systemPrompt,
        string $userMessage,
        int $contextSize = 16384,
        int $timeoutSeconds = 600,
    ): string {
        $content = trim($this->chat($systemPrompt, $userMessage, $timeoutSeconds, [
            'options' => ['num_ctx' => $contextSize],
        ]));

        if ($content === '') {
            Log::warning('Ollama call: model returned empty content');
            throw new OllamaException('The model returned an empty response.');
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $extraPayload
     *
     * @throws OllamaException
     */
    private function chat(string $systemPrompt, string $userMessage, int $timeoutSeconds, array $extraPayload): string
    {
        $url = rtrim(config('services.ollama.url'), '/');
        $model = config('services.ollama.model');

        // PHP's own max_execution_time (default 30s) is a SEPARATE, lower
        // ceiling than Http::timeout() below — it kills the whole process
        // regardless of the client timeout (see the original finding in
        // OllamaJobPostingExtractor). Generous buffer over $timeoutSeconds.
        set_time_limit($timeoutSeconds + 50);

        try {
            $response = Http::timeout($timeoutSeconds)->post("{$url}/api/chat", array_merge([
                'model' => $model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ], $extraPayload));
        } catch (Throwable $e) {
            throw new OllamaException(
                "Couldn't reach Ollama at {$url} — is it running? (`ollama serve`)",
                previous: $e,
            );
        }

        if ($response->failed()) {
            throw new OllamaException(
                "Ollama returned an error (HTTP {$response->status()}): ".$response->body(),
            );
        }

        $content = $response->json('message.content');

        if (! is_string($content)) {
            Log::warning('Ollama call: response had no message.content', ['body' => $response->json()]);
            throw new OllamaException('The model returned a response with no content.');
        }

        return $content;
    }
}
