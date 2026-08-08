<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an accreditation application fails a submission precondition.
 *
 * Carries a validation-friendly message so controllers can surface it beside
 * the relevant field rather than returning a bare 500.
 */
class ApplicationSubmissionException extends RuntimeException
{
    public function __construct(string $message, private readonly string $field = 'application')
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }

    public static function foreignInstitution(): self
    {
        return new self(
            'This application does not belong to your institution.',
            'institution',
        );
    }

    public static function notADraft(): self
    {
        return new self(
            'Only applications in draft status can be modified or submitted.',
            'status',
        );
    }

    public static function missingProgram(): self
    {
        return new self('A training program must be selected.', 'program_id');
    }

    public static function inactiveProgram(): self
    {
        return new self(
            'The selected training program is not active and cannot be submitted.',
            'program_id',
        );
    }

    /**
     * @param  array<int, string>  $steps
     */
    public static function incompleteSteps(array $steps): self
    {
        return new self(
            'Complete these steps before submitting: '.implode(', ', $steps).'.',
            'steps',
        );
    }

    public static function incompleteChecklist(int $outstanding): self
    {
        return new self(
            $outstanding.' mandatory checklist requirement(s) still need a response.',
            'checklist',
        );
    }

    public static function declarationRequired(): self
    {
        return new self(
            'The declaration must be accepted before submitting.',
            'declaration',
        );
    }

    public static function duplicateActiveApplication(): self
    {
        return new self(
            'An active application already exists for this program and application type.',
            'program_id',
        );
    }

    public static function noChecklistVersion(): self
    {
        return new self(
            'No published checklist version is available for this program type. Contact a PSP administrator.',
            'program_id',
        );
    }
}
