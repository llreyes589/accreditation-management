<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicationStatus;
use App\Exceptions\InspectionException;
use App\Models\AccreditationApplication;

/**
 * Determines whether an accreditation application may receive a new inspection.
 *
 * An inspection may be created only when the application exists, is authorized
 * for the acting user, belongs to an active program, is COMPLETE_FOR_INSPECTION,
 * has no unresolved blocking completeness items, has a valid checklist version,
 * and does not already have an active inspection.
 */
class InspectionEligibilityService
{
    public function __construct(
        private readonly ApplicationCompletenessService $completeness,
    ) {}

    /**
     * @throws InspectionException
     */
    public function requireEligible(AccreditationApplication $application): void
    {
        if ($application->program === null || ! $application->program->status->isSelectableForApplication()) {
            throw InspectionException::notEligible('The application\'s program is not active.');
        }

        if ($application->status !== ApplicationStatus::CompleteForInspection) {
            throw InspectionException::notEligible(
                'Only an application marked complete for inspection may be inspected.',
            );
        }

        if ($application->checklistVersion === null) {
            throw InspectionException::notEligible('The application has no valid checklist version.');
        }

        if ($this->completeness->deficiencies($application) !== []) {
            throw InspectionException::notEligible(
                'The application still has unresolved blocking completeness items.',
            );
        }

        if ($application->inspections()->active()->exists()) {
            throw InspectionException::duplicateActive();
        }
    }

    /**
     * Whether the application is eligible (non-throwing; for UI gating).
     */
    public function isEligible(AccreditationApplication $application): bool
    {
        try {
            $this->requireEligible($application);
        } catch (InspectionException) {
            return false;
        }

        return true;
    }
}
