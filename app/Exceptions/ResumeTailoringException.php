<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the resume-tailoring LLM calls (variant selection or
 * tailoring itself) can't be reached or reply with something that isn't
 * valid JSON matching the expected shape. Caught by ResumeTailoringService
 * — a tailoring failure leaves the candidate at its current status rather
 * than aborting the whole ingest batch (JAT-Roadmap-AutoApply.md Phase 3).
 */
class ResumeTailoringException extends Exception
{
}
