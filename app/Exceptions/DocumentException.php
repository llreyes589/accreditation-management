<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain errors raised during document upload, replacement, review, or storage.
 */
class DocumentException extends Exception
{
    public static function uploadFailed(string $reason): self
    {
        return new self('Document upload failed: '.$reason);
    }

    public static function invalidFile(string $reason): self
    {
        return new self('Invalid document file: '.$reason);
    }

    public static function notReplaceable(): self
    {
        return new self('This document is not in a state that accepts a replacement.');
    }

    public static function notReviewable(): self
    {
        return new self('This document is not in a state that accepts a review decision.');
    }

    public static function commentRequired(): self
    {
        return new self('A reviewer comment is required for this decision.');
    }

    public static function alreadyAccepted(): self
    {
        return new self('Accepted document versions are immutable.');
    }

    public static function notFound(): self
    {
        return new self('The requested document could not be found.');
    }
}
