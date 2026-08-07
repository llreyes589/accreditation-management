<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\InstitutionStatus;
use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
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
        $institutionAdmin = $this->user('Institution Administrator', 'institution.admin@example.test');
        $trainingOfficer = $this->user('Training Officer', 'training.officer@example.test');
        $inspector = $this->user('Inspector', 'inspector@example.test');

        // PSP administrator and inspector hold global (cross-institution) roles.
        $this->assignRole($pspAdmin, RoleName::PspAdministrator, null);
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
                'checklist_version' => config('accreditation.checklist_version'),
                'cycle_label' => 'Initial application',
                'status' => ApplicationStatus::Draft,
                'current_step' => WizardStep::ProgramSelection,
                'completion_percentage' => 0,
                'created_by' => $institutionAdmin->id,
            ],
        );
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
