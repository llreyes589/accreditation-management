<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised for illegal checklist-version lifecycle transitions.
 */
class ChecklistVersionException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public static function cannotPublish(array $errors): self
    {
        return new self('This version cannot be published: '.implode(' ', $errors));
    }

    public static function cannotRetire(string $currentStatus): self
    {
        return new self("Only a PUBLISHED version can be retired (current status: {$currentStatus}).");
    }

    public static function cannotDelete(string $currentStatus): self
    {
        return new self("Only an unused DRAFT version can be deleted (current status: {$currentStatus}).");
    }

    public static function inUse(): self
    {
        return new self('This version is referenced by one or more applications and cannot be deleted.');
    }

    public static function noPublishedVersion(): self
    {
        return new self('No published checklist version is available for this program type.');
    }
}
