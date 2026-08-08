<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;

/**
 * Computes supporting-document completeness for an accreditation application.
 *
 * Completeness is a workflow indicator only: it never auto-approves an
 * application. A requirement that requires a document is "document-complete"
 * only when it has an accepted active document version.
 */
class DocumentCompletenessService
{
    /**
     * Per-requirement document-completeness state.
     */
    public const MISSING = 'missing';

    public const UPLOADED = 'uploaded';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const REPLACEMENT_REQUESTED = 'replacement_requested';

    public const OPTIONAL_NOT_SUBMITTED = 'optional_not_submitted';

    /**
     * @return array{
     *     total_required: int,
     *     missing: int,
     *     uploaded_or_under_review: int,
     *     accepted: int,
     *     rejected: int,
     *     replacement_requested: int,
     *     complete: bool,
     * }
     */
    public function summary(AccreditationApplication $application): array
    {
        $counts = [
            'total_required' => 0,
            'missing' => 0,
            'uploaded_or_under_review' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'replacement_requested' => 0,
            'complete' => true,
        ];

        foreach ($application->requirements()->with(['requirement', 'documents'])->get() as $requirement) {
            if (! ($requirement->requirement->requires_document ?? false)) {
                continue;
            }

            $state = $this->requirementState($requirement);
            $counts['total_required']++;

            match ($state) {
                self::MISSING, self::REJECTED, self::REPLACEMENT_REQUESTED => $counts['complete'] = false,
                default => null,
            };

            match ($state) {
                self::MISSING => $counts['missing']++,
                self::UPLOADED => $counts['uploaded_or_under_review']++,
                self::ACCEPTED => $counts['accepted']++,
                self::REJECTED => $counts['rejected']++,
                self::REPLACEMENT_REQUESTED => $counts['replacement_requested']++,
                default => null,
            };
        }

        return $counts;
    }

    /**
     * Document-completeness state for a single requirement.
     */
    public function requirementState(ApplicationRequirement $requirement): string
    {
        $active = $requirement->documents()
            ->whereNotIn('status', [DocumentStatus::Withdrawn, DocumentStatus::Superseded])
            ->get();

        if ($active->isEmpty()) {
            return $requirement->requirement?->is_mandatory
                ? self::MISSING
                : self::OPTIONAL_NOT_SUBMITTED;
        }

        if ($active->contains(fn ($doc): bool => $doc->isAccepted())) {
            return self::ACCEPTED;
        }

        if ($active->contains(fn ($doc): bool => $doc->status === DocumentStatus::Rejected)) {
            return self::REJECTED;
        }

        if ($active->contains(fn ($doc): bool => $doc->status === DocumentStatus::ReplacementRequested)) {
            return self::REPLACEMENT_REQUESTED;
        }

        return self::UPLOADED;
    }

    /**
     * Whether every document-requiring requirement on the application is
     * document-complete (has an accepted active document).
     */
    public function isComplete(AccreditationApplication $application): bool
    {
        return $this->summary($application)['complete'];
    }
}
