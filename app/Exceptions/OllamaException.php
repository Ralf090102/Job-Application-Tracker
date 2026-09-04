<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by OllamaClient when a call to the local Ollama server fails —
 * unreachable, an HTTP error, or a response that isn't valid JSON/empty
 * when content was expected. Every caller (OllamaJobPostingExtractor,
 * OllamaJobPostingMatcher, OllamaResumeTailor) catches this and rewraps it
 * into its own documented exception type, so each interface's contract
 * stays unchanged by this shared client existing underneath it.
 */
class OllamaException extends Exception
{
}
