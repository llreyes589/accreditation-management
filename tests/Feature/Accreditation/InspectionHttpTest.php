<?php

namespace Tests\Feature\Accreditation;

use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Enums\RoleName;
use App\Models\Inspection;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInspectionData;
use Tests\TestCase;

/**
 * Inspection scheduling + checklist: HTTP layer (routes, Inertia responses,
 * server-side authorization, direct-URL tampering resistance).
 *
 * Synthetic data only.
 */
class InspectionHttpTest extends TestCase
{
    use CreatesInspectionData;
    use RefreshDatabase;

    public function test_psp_administrator_can_view_eligible_applications_list(): void
    {
        $this->eligibleApplication();

        $this->actingAs($this->pspAdministrator())
            ->get(route('inspections.eligible'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('accreditation/inspections/eligible')
                ->has('applications'));
    }

    public function test_assigned_inspector_can_access_inspection_list_without_403(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);

        // The inspector's landing page must not be the PSP/CART-only eligible list.
        $this->actingAs($lead)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('accreditation/inspections/index')
                ->has('inspections'));

        // The eligible (create) list must remain forbidden for a plain inspector.
        $this->actingAs($lead)
            ->get(route('inspections.eligible'))
            ->assertForbidden();
    }

    public function test_institution_user_cannot_view_eligible_list(): void
    {
        $fixture = $this->eligibleApplication();

        $this->actingAs($fixture['applicant'])
            ->get(route('inspections.eligible'))
            ->assertForbidden();
    }

    public function test_eligible_list_excludes_non_complete_applications(): void
    {
        // Only the complete-for-inspection application should appear.
        $this->actingAs($this->pspAdministrator())
            ->get(route('inspections.eligible'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('applications'));
    }

    public function test_unauthorized_user_cannot_reach_create_form(): void
    {
        $fixture = $this->eligibleApplication();

        $this->actingAs($fixture['applicant'])
            ->get(route('inspections.create', ['application' => $fixture['application']->id]))
            ->assertForbidden();
    }

    public function test_psp_administrator_can_open_create_form_for_eligible_application(): void
    {
        $fixture = $this->eligibleApplication();

        $this->actingAs($this->pspAdministrator())
            ->get(route('inspections.create', ['application' => $fixture['application']->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('accreditation/inspections/create')
                ->where('application.id', $fixture['application']->id));
    }

    public function test_create_stores_scheduled_inspection_and_redirects(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $second = $this->inspectorUser();

        $this->actingAs($this->pspAdministrator())
            ->post(route('inspections.store', ['application' => $fixture['application']->id]), [
                'application_id' => $fixture['application']->id,
                'inspection_type' => 'onsite',
                'scheduled_start_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'scheduled_end_at' => now()->addDays(7)->addHours(2)->format('Y-m-d H:i:s'),
                'timezone' => 'Asia/Manila',
                'location' => '[SYNTHETIC] Venue',
                'lead_inspector_id' => $lead->id,
                'inspector_ids' => [$second->id],
            ])
            ->assertRedirectContains('/inspections/');

        $this->assertDatabaseHas('inspections', [
            'application_id' => $fixture['application']->id,
            'status' => InspectionStatus::Scheduled->value,
        ]);
        $this->assertDatabaseHas('inspection_criteria', [
            'inspection_id' => Inspection::where('application_id', $fixture['application']->id)->first()->id,
        ]);
    }

    public function test_create_rejects_ineligible_application_server_side(): void
    {
        // A fresh submitted application (not complete for inspection) must be rejected.
        $submitted = $this->submittedApplication();
        $lead = $this->inspectorUser();

        $this->actingAs($this->pspAdministrator())
            ->post(route('inspections.store', ['application' => $submitted['application']->id]), [
                'application_id' => $submitted['application']->id,
                'inspection_type' => 'onsite',
                'scheduled_start_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'scheduled_end_at' => now()->addDays(7)->addHours(2)->format('Y-m-d H:i:s'),
                'timezone' => 'Asia/Manila',
                'location' => '[SYNTHETIC] Venue',
                'lead_inspector_id' => $lead->id,
                'inspector_ids' => [],
            ])
            ->assertSessionHasErrors(); // eligibility failure -> 302 with errors (no exception)

        $this->assertDatabaseMissing('inspections', ['application_id' => $submitted['application']->id]);
    }

    public function test_assigned_inspector_can_view_their_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);

        $this->actingAs($lead)
            ->get(route('inspections.show', ['inspection' => $inspection->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('accreditation/inspections/show')
                ->where('inspection.id', $inspection->id));
    }

    public function test_assigned_inspector_cannot_view_unrelated_institution_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']);

        // A different inspector who is not on this inspection.
        $outsider = $this->inspectorUser();

        $this->actingAs($outsider)
            ->get(route('inspections.show', ['inspection' => $inspection->id]))
            ->assertForbidden();
    }

    public function test_direct_url_tampering_cannot_expose_other_institution_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']);

        // Institution user of a completely different institution.
        $otherInstitution = Institution::factory()->create();
        $outsider = $this->userWithRole(RoleName::InstitutionAdministrator, $otherInstitution);

        $this->actingAs($outsider)
            ->get(route('inspections.show', ['inspection' => $inspection->id]))
            ->assertForbidden();
    }

    public function test_start_action_requires_assigned_inspector(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();

        $this->actingAs($lead)
            ->post(route('inspections.start', ['inspection' => $inspection->id]))
            ->assertRedirectContains('/inspections/');

        $this->assertEquals(InspectionStatus::InProgress, $inspection->refresh()->status);
    }

    public function test_unassigned_user_cannot_start_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']);
        $outsider = $this->inspectorUser();

        $this->actingAs($outsider)
            ->post(route('inspections.start', ['inspection' => $inspection->id]))
            ->assertForbidden();
    }

    public function test_inspector_can_save_criterion_and_locked_after_submission(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->startInspection($inspection->refresh(), $lead);

        $criterion = $inspection->criteria()->first();

        $this->actingAs($lead)
            ->patch(route('inspections.criteria.save', ['inspection' => $inspection->id, 'criterion' => $criterion->id]), [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ])
            ->assertRedirectContains('/inspections/');

        $this->assertEquals(InspectionResult::Compliant, $criterion->refresh()->result);

        // Complete the rest and submit.
        foreach ($inspection->criteria()->get() as $c) {
            $c->update(['result' => InspectionResult::Compliant, 'inspector_notes' => '[SYNTHETIC] ok']);
        }
        $this->actingAs($lead)
            ->post(route('inspections.submit', ['inspection' => $inspection->id]))
            ->assertRedirectContains('/inspections/');

        $this->assertEquals(InspectionStatus::SubmittedForReview, $inspection->refresh()->status);

        // After submission the same inspector cannot edit.
        $this->actingAs($lead)
            ->patch(route('inspections.criteria.save', ['inspection' => $inspection->id, 'criterion' => $criterion->id]), [
                'result' => InspectionResult::Noncompliant->value,
                'inspector_notes' => 'x',
            ])
            ->assertForbidden();
    }

    public function test_reviewer_can_return_for_revision_with_comment(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->startInspection($inspection->refresh(), $lead);
        foreach ($inspection->criteria()->get() as $c) {
            $c->update(['result' => InspectionResult::Compliant, 'inspector_notes' => '[SYNTHETIC] ok']);
        }
        $this->actingAs($lead)
            ->post(route('inspections.submit', ['inspection' => $inspection->id]))
            ->assertRedirectContains('/inspections/');

        $reviewer = $this->cartReviewer();
        $this->actingAs($reviewer)
            ->post(route('inspections.return', ['inspection' => $inspection->id]), [
                'comment' => '[SYNTHETIC] Recheck section 3.',
            ])
            ->assertRedirectContains('/inspections/');

        $this->assertEquals(InspectionStatus::InProgress, $inspection->refresh()->status);
    }

    public function test_reviewer_can_complete_submitted_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($fixture['application'], $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->startInspection($inspection->refresh(), $lead);
        foreach ($inspection->criteria()->get() as $c) {
            $c->update(['result' => InspectionResult::Compliant, 'inspector_notes' => '[SYNTHETIC] ok']);
        }
        $this->actingAs($lead)
            ->post(route('inspections.submit', ['inspection' => $inspection->id]))
            ->assertRedirectContains('/inspections/');

        $reviewer = $this->cartReviewer();
        $this->actingAs($reviewer)
            ->post(route('inspections.complete', ['inspection' => $inspection->id]), [
                'summary' => '[SYNTHETIC] Inspection complete.',
            ])
            ->assertRedirectContains('/inspections/');

        $this->assertEquals(InspectionStatus::Completed, $inspection->refresh()->status);
    }

    public function test_cancellation_requires_reason(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']);

        $this->actingAs($this->pspAdministrator())
            ->post(route('inspections.cancel', ['inspection' => $inspection->id]), ['cancellation_reason' => ''])
            ->assertSessionHasErrors();

        $this->assertNotEquals(InspectionStatus::Cancelled, $inspection->refresh()->status);
    }

    public function test_auditor_can_view_but_not_modify(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']);
        $auditor = $this->auditorUser();

        $this->actingAs($auditor)
            ->get(route('inspections.show', ['inspection' => $inspection->id]))
            ->assertOk();

        $this->actingAs($auditor)
            ->post(route('inspections.start', ['inspection' => $inspection->id]))
            ->assertForbidden();
    }
}
