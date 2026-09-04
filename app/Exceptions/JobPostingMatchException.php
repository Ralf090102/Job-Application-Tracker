<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the LLM rubric-matching service can't be reached, or replies
 * with something that isn't valid JSON matching the expected shape. Caught
 * by AutoApplyIngestService — a single bad match call shouldn't abort the
 * whole ingest batch (JAT-Roadmap-AutoApply.md Phase 2).
 */
class JobPostingMatchException extends Exception
{
}
