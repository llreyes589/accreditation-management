<?php

namespace App\Services\Accreditation;

use App\Models\AccreditationApplication;
use App\Services\Documents\DocumentCompletenessService;

/**
 * Unified application-completeness calculation for the reviewer workflow.
 *
 * Completeness is an administrative and documentary check only: it never
 * determines final compliance, accreditation eligibility, or any decision.
 * It reuses the existing checklist and document calculators so the numbers
 * stay consistent with the wizard submission gate.
 *
 * A blocking item is anything that still prevents the application from being
 * ready for inspection: an outstanding mandatory checklist response, or a
 * required supporting document that is missing, rejected, or has a replacement
 * requested.
 */
class ApplicationCompletenessService
{
    public function __construct(
        private readonly ApplicationProgressCalculator $progress,
        private readonly DocumentCompletenessService $documents = new DocumentCompletenessService,
    ) {}

    /**
     * Full completeness breakdown for the reviewer dashboard / detail page.
     *
     * @return array<string, mixed>
     */
    public function summary(AccreditationApplication $application): array
    {
        $document = $this->documents->summary($application);
        $checklistOutstanding = $this->progress->outstandingMandatoryCount($application);

        $blocking = $this->deficiencies($application);

        return [
            'checklist_outstanding_mandatory' => $checklistOutstanding,
            'documents' => $document,
            'blocking_item_count' => count($blocking),
            'is_complete' => $blocking === [],
            'deficiencies' => $blocking,
        ];
    }

    /**
     * Structured list of items that still block readiness for inspection.
     *
     * Each entry has a category (checklist | document), an identifying code /
     * title, the blocking state, and a human-readable detail. Reviewers use
     * this both to decide and to communicate revisions to the applicant.
     *
     * @return array<int, array<string, string>>
     */
    public function deficiencies(AccreditationApplication $application): array
    {
        $items = [];

        foreach ($this->checklistDeficiencies($application) as $item) {
            $items[] = $item;
        }

        foreach ($this->documentDeficiencies($application) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function checklistDeficiencies(AccreditationApplication $application): array
    {
        $requirements = $application->requirements()
            ->with('requirement')
            ->get()
            ->filter(fn ($row): bool => (bool) $row->requirement?->is_mandatory);

        $items = [];

        foreach ($requirements as $row) {
            if ($this->progress->isSatisfied($row)) {
                continue;
            }

            $items[] = [
                'category' => 'checklist',
                'code' => $row->requirement->code ?? 'REQ',
                'title' => $row->requirement->requirement ?? 'Mandatory requirement',
                'state' => 'outstanding',
                'detail' => 'Mandatory checklist response is missing or incomplete.',
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function documentDeficiencies(AccreditationApplication $application): array
    {
        $items = [];

        foreach ($application->requirements()->with(['requirement', 'documents'])->get() as $requirement) {
            if (! ($requirement->requirement->requires_document ?? false)) {
                continue;
            }

            $state = $this->documents->requirementState($requirement);

            if ($state === DocumentCompletenessService::ACCEPTED) {
                continue;
            }

            $items[] = [
                'category' => 'document',
                'code' => $requirement->requirement->code ?? 'DOC',
                'title' => $requirement->requirement->requirement ?? 'Supporting document',
                'state' => $state,
                'detail' => $this->documentDetail($state),
            ];
        }

        return $items;
    }

    private function documentDetail(string $state): string
    {
        return match ($state) {
            DocumentCompletenessService::MISSING => 'Required supporting document has not been uploaded.',
            DocumentCompletenessService::REJECTED => 'Uploaded document was rejected by a reviewer.',
            DocumentCompletenessService::REPLACEMENT_REQUESTED => 'A replacement document has been requested.',
            DocumentCompletenessService::UPLOADED => 'Document uploaded but not yet accepted by a reviewer.',
            default => 'Document is not yet complete.',
        };
    }

    /**
     * Whether the application has any blocking item preventing it from being
     * marked complete for inspection.
     */
    public function isComplete(AccreditationApplication $application): bool
    {
        return $this->deficiencies($application) === [];
    }
}
