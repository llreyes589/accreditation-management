<?php

namespace Tests\Concerns;

use App\Models\AccreditationApplication;
use App\Models\Finding;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Models\Institution;
use App\Models\User;
use App\Services\Accreditation\FindingService;
use App\Services\Accreditation\InspectionService;

/**
 * Builds synthetic findings fixtures on top of the inspection fixtures.
 *
 * All data is fictional; no real institution or PSP criteria are used.
 */
trait CreatesFindingData
{
    use CreatesInspectionData;

    /**
     * A completed inspection with snapshotted criteria, ready to raise findings.
     *
     * @return array{application: AccreditationApplication, inspection: Inspection, criterion: InspectionCriterion, reviewer: User, institution: Institution, applicant: User}
     */
    protected function completedInspectionWithCriterion(): array
    {
        $fixture = $this->eligibleApplication();
        $application = $fixture['application'];
        $institution = $fixture['institution'];
        $applicant = $fixture['applicant'];

        $inspection = $this->scheduledInspection($application);
        $inspection = $this->startInspection($inspection);

        // Record a result on every criterion so the inspection can be submitted.
        $criterion = $inspection->criteria->first();
        $inspection->criteria->each(fn ($c) => $c->update([
            'result' => 'compliant',
            'inspector_notes' => '[SYNTHETIC] Inspector noted.',
        ]));
        // Make the first one noncompliant so a finding has something to raise.
        $criterion->update([
            'result' => 'noncompliant',
            'inspector_notes' => '[SYNTHETIC] Inspector noted noncompliance.',
        ]);

        // Complete the inspection (this freezes the snapshot).
        $inspection = app(InspectionService::class)->submit(
            $this->pspAdministrator(),
            $inspection,
        );
        $inspection = app(InspectionService::class)->complete(
            $this->pspAdministrator(),
            $inspection,
            '[SYNTHETIC] Inspection complete.',
        );

        $criterion->refresh();

        return [
            'application' => $application,
            'inspection' => $inspection,
            'criterion' => $criterion,
            'reviewer' => $fixture['reviewer'],
            'institution' => $institution,
            'applicant' => $applicant,
        ];
    }

    /**
     * A draft finding raised from a completed inspection criterion.
     *
     * @return array{finding: Finding, inspection: Inspection, criterion: InspectionCriterion, reviewer: User, institution: Institution, applicant: User}
     */
    protected function draftFinding(): array
    {
        $data = $this->completedInspectionWithCriterion();
        $finding = app(FindingService::class)->create(
            $this->pspAdministrator(),
            $data['inspection'],
            [
                'title' => '[SYNTHETIC] Finding title',
                'description' => '[SYNTHETIC] Finding description.',
                'category' => 'minor',
                'severity' => 'high',
            ],
            $data['criterion'],
        );

        return [
            'finding' => $finding,
            'inspection' => $data['inspection'],
            'criterion' => $data['criterion'],
            'reviewer' => $data['reviewer'],
            'institution' => $data['institution'],
            'applicant' => $data['applicant'],
        ];
    }
}
