<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the extraction service can't be reached, or replies with
 * something that isn't valid JSON matching the expected shape. Caught in
 * the controller and turned into a clear error response — the frontend
 * should never lose the user's pasted text over this (Roadmap.md Phase 5).
 */
class JobPostingExtractionException extends Exception
{
}
