<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when JobSearchClient can't reach JSearch, gets an error response,
 * or the active job_search_criteria row has nothing to search with.
 */
class JobSearchException extends Exception
{
}
