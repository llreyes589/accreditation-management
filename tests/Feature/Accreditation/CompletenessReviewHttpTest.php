<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicationStatus;
use App\Services\Accreditation\CompletenessReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCompletenessData;
use Tests\TestCase;

/**
 * HTTP layer for the completeness-review workflow: routes, policies, and the
 * Inertia responses for the reviewer dashboard/detail and applicant resubmit.
 *
 * Synthetic data only.
 */
class CompletenessReviewHttpTest extends TestCase
{
    use CreatesCompletenessData;
    use RefreshDatabase;

    public function test_reviewer_dashboard_lists_reviewable_applications(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];

        $this->actingAs($reviewer)
            ->get('/reviewer/completeness/applications')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('accreditation/reviewer/completeness/dashboard')
                    ->has('applications', 1),
            );
    }

    public function test_non_reviewer_cannot_view_dashboard(): void
    {
        $actors = $this->submittedApplication();
        $applicant = $actors['applicant'];

        $this->actingAs($applicant)
            ->get('/reviewer/completeness/applications')
            ->assertForbidden();
    }

    public function test_reviewer_can_begin_review_via_route(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        $this->actingAs($reviewer)
            ->post("/reviewer/completeness/applications/{$application->id}/begin")
            ->assertRedirect();

        $this->assertTrue(
            $application->refresh()->status->isUnderCompletenessReview(),
        );
    }

    public function test_return_for_revision_requires_comment_via_route(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        app(CompletenessReviewService::class)
            ->beginReview($reviewer, $application);

        $this->actingAs($reviewer)
            ->post("/reviewer/completeness/applications/{$application->id}/return", [
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');

        $this->assertTrue(
            $application->refresh()->status->isUnderCompletenessReview(),
        );
    }

    public function test_applicant_resubmit_route_moves_returned_to_submitted(): void
    {
        Storage::fake('accreditation');
        $actors = $this->submittedApplication();
        $applicant = $actors['applicant'];
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);
        $service->returnForRevision(
            $reviewer,
            $application->refresh(),
            '[SYNTHETIC] Please revise.',
        );

        $this->actingAs($applicant)
            ->post("/applications/{$application->refresh()->id}/resubmit")
            ->assertRedirect();

        $this->assertTrue(
            $application->refresh()->status === ApplicationStatus::Submitted,
        );
    }

    public function test_reviewer_cannot_resubmit_applicant_application(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);
        $service->returnForRevision(
            $reviewer,
            $application->refresh(),
            '[SYNTHETIC] Please revise.',
        );

        // A cross-institution reviewer must NOT be able to resubmit on the
        // institution's behalf.
        $this->actingAs($reviewer)
            ->post("/applications/{$application->refresh()->id}/resubmit")
            ->assertForbidden();
    }

    public function test_completeness_detail_page_renders_for_reviewer(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        app(CompletenessReviewService::class)
            ->beginReview($reviewer, $application);

        $this->actingAs($reviewer)
            ->get("/reviewer/completeness/applications/{$application->refresh()->id}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('accreditation/reviewer/completeness/show')
                    ->has('summary.deficiencies'),
            );
    }

    public function test_complete_for_inspection_blocked_with_deficiencies_via_route(): void
    {
        $actors = $this->submittedApplication();
        $reviewer = $actors['reviewer'];
        $application = $actors['application'];

        $this->addDocumentRequirement($application);

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);

        $this->actingAs($reviewer)
            ->post("/reviewer/completeness/applications/{$application->refresh()->id}/complete")
            ->assertSessionHasErrors('completeness');

        $this->assertTrue(
            $application->refresh()->status->isUnderCompletenessReview(),
        );
    }
}
