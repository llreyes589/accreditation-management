<?php

namespace Tests\Feature\Accreditation;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Services\Accreditation\CorrectiveActionService;
use App\Services\Accreditation\FindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFindingData;
use Tests\TestCase;

/**
 * Findings & corrective-action: HTTP layer (routes, Inertia responses,
 * server-side authorization, direct-URL tampering resistance).
 *
 * Synthetic data only.
 */
class FindingHttpTest extends TestCase
{
    use CreatesFindingData;
    use RefreshDatabase;

    public function test_psp_administrator_can_view_findings_dashboard(): void
    {
        $this->draftFinding();

        $this->actingAs($this->pspAdministrator())
            ->get(route('findings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('accreditation/findings/index')
                ->has('findings'));
    }

    public function test_auditor_can_view_but_not_create(): void
    {
        $this->actingAs($this->auditorUser())
            ->get(route('findings.index'))
            ->assertOk();

        $data = $this->completedInspectionWithCriterion();

        $this->actingAs($this->auditorUser())
            ->post(route('findings.store'), [
                'inspection_id' => $data['inspection']->id,
                'title' => 'X',
                'category' => 'minor',
            ])
            ->assertForbidden();
    }

    public function test_institution_user_sees_only_own_findings(): void
    {
        $own = $this->draftFinding();
        $other = $this->draftFinding();

        $this->actingAs($own['institution']->users()->first())
            ->get(route('findings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('findings', fn ($findings) => collect($findings)
                    ->every(fn ($f) => $f['id'] === $own['finding']->id
                        || $f['id'] !== $other['finding']->id)));
    }

    public function test_institution_user_cannot_open_finding(): void
    {
        $data = $this->draftFinding();
        $institutionUser = $data['institution']->users()->first();

        $this->actingAs($institutionUser)
            ->post(route('findings.open', $data['finding']))
            ->assertForbidden();
    }

    public function test_institution_user_acknowledges_own_finding(): void
    {
        $data = $this->draftFinding();
        $institutionUser = $data['institution']->users()->first();

        // The finding must be published (OPEN) by a reviewer before the
        // institution can acknowledge it (DRAFT -> OPEN -> ACKNOWLEDGED).
        app(FindingService::class)
            ->open($this->pspAdministrator(), $data['finding']);

        $response = $this->actingAs($institutionUser)
            ->post(route('findings.acknowledge', $data['finding']));

        $response->assertRedirect(route('findings.show', $data['finding']));

        $this->assertSame(FindingStatus::Acknowledged, $data['finding']->fresh()->status);
    }

    public function test_create_requires_completed_inspection(): void
    {
        $fixture = $this->eligibleApplication();
        $inspection = $this->scheduledInspection($fixture['application']); // not completed
        $inspection = $this->startInspection($inspection);

        $this->actingAs($this->pspAdministrator())
            ->post(route('findings.store'), [
                'inspection_id' => $inspection->id,
                'title' => '[SYNTHETIC] X',
                'category' => 'minor',
            ])
            ->assertSessionHasErrors();
    }

    public function test_tampering_with_another_institutions_finding_is_forbidden(): void
    {
        $own = $this->draftFinding();
        $other = $this->draftFinding();
        $intruder = $own['institution']->users()->first();

        // The intruder belongs to `own`'s institution, not `other`'s.
        $this->actingAs($intruder)
            ->get(route('findings.show', $other['finding']))
            ->assertForbidden();
    }

    public function test_psp_can_open_and_resolve_finding_via_http(): void
    {
        $data = $this->draftFinding();
        $psp = $this->pspAdministrator();

        $this->actingAs($psp)
            ->post(route('findings.open', $data['finding']))
            ->assertRedirect(route('findings.show', $data['finding']));

        $this->assertSame(FindingStatus::Open, $data['finding']->fresh()->status);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $data = $this->draftFinding();

        $this->get(route('findings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_corrective_action_submission_requires_institution_member(): void
    {
        $data = $this->draftFinding();
        $open = app(FindingService::class)
            ->open($this->pspAdministrator(), $data['finding']);
        $finding = app(FindingService::class)
            ->requireCorrectiveAction($this->pspAdministrator(), $open);
        $action = app(CorrectiveActionService::class)
            ->create($this->pspAdministrator(), $finding, [
                'title' => 'CA',
                'responsible_user_id' => $data['applicant']->id,
                'due_date' => now()->addDays(14)->toDateString(),
            ]);
        $action = app(CorrectiveActionService::class)
            ->start($data['applicant'], $action);

        // A PSP admin (not the responsible institution) must not submit the response.
        $this->actingAs($this->pspAdministrator())
            ->post(route('findings.actions.submit', [$finding, $action]), [
                'proposed_action' => 'x',
            ])
            ->assertForbidden();

        // The institution applicant can submit.
        $this->actingAs($data['applicant'])
            ->post(route('findings.actions.submit', [$finding, $action]), [
                'proposed_action' => '[SYNTHETIC] Done',
            ])
            ->assertRedirect(route('findings.show', $finding));
    }
}
