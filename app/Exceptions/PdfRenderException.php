<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when the Proteus CLI isn't found, exits non-zero, or doesn't
 * produce the expected output file.
 */
class PdfRenderException extends Exception
{
}
