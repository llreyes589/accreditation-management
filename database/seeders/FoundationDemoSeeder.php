<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InspectionResult;
use App\Enums\InstitutionStatus;
use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use App\Services\Accreditation\FindingService;
use App\Services\Accreditation\InspectionService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic local demonstration data.
 *
 * All values are fictional. No production credentials, no real institution
 * details, and no official PSP accreditation criteria are represented here.
 */
class FoundationDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Shared password for local demo accounts only.
     */
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $institution = Institution::updateOrCreate(
            ['code' => 'DEMO-0001'],
            [
                'name' => 'Demo General Hospital',
                'status' => InstitutionStatus::Active,
                'contact_email' => 'training@demo-general.test',
                'contact_phone' => '+63-02-000-0000',
                'address' => '1 Demo Avenue, Sample City',
            ],
        );

        // One combined AP/CP program. Accreditation validity is left unset —
        // dates are only recorded by an authorized decision.
        $program = Program::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'program_type' => ProgramType::CombinedPathology,
            ],
            [
                'name' => ProgramType::CombinedPathology->label().' Residency Training Program',
                'status' => ProgramStatus::Active,
            ],
        );

        $pspAdmin = $this->user('PSP Administrator', 'psp.admin@example.test');
        $cartReviewer = $this->user('CART Reviewer', 'cart.reviewer@example.test');
        $institutionAdmin = $this->user('Institution Administrator', 'institution.admin@example.test');
        $trainingOfficer = $this->user('Training Officer', 'training.officer@example.test');
        $inspector = $this->user('Inspector', 'inspector@example.test');

        // PSP administrator, CART reviewer and inspector hold global
        // (cross-institution) roles. PSP/CART reviewers may create, schedule and
        // review inspections; inspectors only conduct assigned inspections.
        $this->assignRole($pspAdmin, RoleName::PspAdministrator, null);
        $this->assignRole($cartReviewer, RoleName::CartReviewer, null);
        $this->assignRole($inspector, RoleName::Inspector, null);

        // Institution-scoped users are also institution members.
        $institutionAdmin->institutions()->syncWithoutDetaching([
            $institution->id => ['is_primary' => true],
        ]);
        $trainingOfficer->institutions()->syncWithoutDetaching([
            $institution->id => ['is_primary' => true],
        ]);

        $this->assignRole($institutionAdmin, RoleName::InstitutionAdministrator, $institution->id);
        $this->assignRole($trainingOfficer, RoleName::TrainingOfficer, $institution->id);

        AccreditationApplication::updateOrCreate(
            ['application_reference' => 'DEMO-APP-0001'],
            [
                'institution_id' => $institution->id,
                'program_id' => $program->id,
                'application_type' => ApplicationType::New,
                // Use a synthetic checklist version that actually contains
                // sections + requirements so the inspection snapshot and the
                // findings raised from its criteria have real content.
                'checklist_version' => 'SYN-combined_pathology',
                'checklist_version_id' => ChecklistVersion::where('version_code', 'SYN-combined_pathology')
                    ->first()?->id,
                'cycle_label' => 'Initial application',
                'status' => ApplicationStatus::Draft,
                'current_step' => WizardStep::ProgramSelection,
                'completion_percentage' => 0,
                'created_by' => $institutionAdmin->id,
            ],
        );

        // Mark the demo application complete-for-inspection so a CART reviewer can
        // immediately schedule an inspection against it. This is demo seed data;
        // real applications reach this state via the completeness-review workflow
        // (ApplicationWizardService::submit -> CompletenessReviewService::markComplete).
        $demoApp = AccreditationApplication::where('application_reference', 'DEMO-APP-0001')->firstOrFail();
        if ($demoApp->status !== ApplicationStatus::CompleteForInspection) {
            $demoApp->forceFill([
                'status' => ApplicationStatus::CompleteForInspection,
                'completed_for_inspection_at' => now(),
                'completed_for_inspection_by' => $cartReviewer->id,
            ])->save();
        }

        // Build a completed inspection with snapshotted criteria and a couple of
        // demo findings so the Findings dashboard is not empty for reviewers.
        // Guarded so a re-run does not duplicate the inspection/findings.
        if (Inspection::where('location', 'LIKE', '%[SYNTHETIC] Demo%')->doesntExist()) {
            $this->seedDemoInspectionAndFindings($demoApp, $cartReviewer, $inspector);
        }
    }

    /**
     * Drive DEMO-APP-0001 through a full inspection cycle and raise two findings
     * so the Findings dashboard has demonstrable content. All data is fictional.
     */
    private function seedDemoInspectionAndFindings(
        AccreditationApplication $application,
        User $reviewer,
        User $inspector,
    ): void {
        $inspectionService = app(InspectionService::class);
        $findingService = app(FindingService::class);

        $inspection = $inspectionService->create(
            $reviewer,
            $application,
            [
                'inspection_type' => 'onsite',
                'scheduled_start_at' => now()->addDays(7),
                'scheduled_end_at' => now()->addDays(7)->addHours(2),
                'timezone' => 'Asia/Manila',
                'location' => '[SYNTHETIC] Demo Inspection Venue',
            ],
            $inspector->id,
            [],
        )['inspection'];

        $inspectionService->start($inspector, $inspection);

        // Record a result on every snapshotted criterion (required before an
        // inspection can be submitted for review). The first two are marked
        // deficient so the demo findings below have a real source criterion.
        $criteria = $inspection->criteria()->orderBy('display_order')->get();
        foreach ($criteria as $index => $criterion) {
            $result = $index < 2 ? InspectionResult::Noncompliant : InspectionResult::Compliant;
            $inspectionService->saveCriterion($inspector, $inspection, $criterion, [
                'result' => $result->value,
                'inspector_notes' => '[SYNTHETIC] Inspector observation for criterion.',
            ]);
        }

        $inspectionService->submit($inspector, $inspection);
        $inspectionService->complete($reviewer, $inspection, '[SYNTHETIC] Inspection completed during demo seeding.');

        // Raise two findings from the recorded criteria and publish them.
        $criteria = $inspection->criteria()->orderBy('display_order')->take(2)->get();
        $findingSpecs = [
            [
                'category' => 'major',
                'title' => '[SYNTHETIC] Incomplete documentation retention policy',
                'description' => '[SYNTHETIC] The program could not demonstrate a current documentation retention policy.',
                'due_date' => now()->addDays(30),
            ],
            [
                'category' => 'minor',
                'title' => '[SYNTHETIC] Missing sign-off on competency assessment',
                'description' => '[SYNTHETIC] One competency assessment lacked the required faculty sign-off.',
                'due_date' => now()->addDays(14),
            ],
        ];

        foreach ($criteria as $index => $criterion) {
            $finding = $findingService->create($reviewer, $inspection, $findingSpecs[$index], $criterion);
            $findingService->open($reviewer, $finding);
        }
    }

    private function user(string $name, string $email): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );
    }

    private function assignRole(User $user, RoleName $role, ?int $institutionId): void
    {
        $roleModel = Role::where('name', $role->value)->firstOrFail();

        $exists = $user->roles()
            ->wherePivot('role_id', $roleModel->id)
            ->wherePivot('institution_id', $institutionId)
            ->exists();

        if (! $exists) {
            $user->roles()->attach($roleModel->id, ['institution_id' => $institutionId]);
        }
    }
}
