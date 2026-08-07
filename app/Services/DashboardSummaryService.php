<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the authenticated dashboard summary.
 *
 * Institution-scoped users see only their own institution; users with a
 * cross-institution role see system-wide totals.
 */
class DashboardSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $user->loadMissing(['institutions', 'roles']);

        $isCrossInstitution = $user->hasCrossInstitutionRole();
        $institution = $user->primaryInstitution();

        $programs = $this->programsFor($user, $institution, $isCrossInstitution);
        $applicationCount = $this->applicationCountFor($institution, $isCrossInstitution);
        $activeAccreditation = $this->activeAccreditation($programs);

        return [
            'scope' => $isCrossInstitution ? 'system' : 'institution',
            'roles' => $user->roles->map(fn ($role): array => [
                'name' => $role->name,
                'label' => $role->label,
            ])->values()->all(),
            'institution' => $institution === null ? null : [
                'id' => $institution->id,
                'name' => $institution->name,
                'code' => $institution->code,
                'status' => $institution->status->label(),
            ],
            'programs' => $programs->map(fn (Program $program): array => [
                'id' => $program->id,
                'name' => $program->name,
                'type' => $program->program_type->label(),
                'status' => $program->status->label(),
                'accredited_until' => $program->accredited_until?->toDateString(),
                'has_active_accreditation' => $program->hasActiveAccreditation(),
            ])->values()->all(),
            'application_count' => $applicationCount,
            'open_application_count' => $this->openApplicationCount($institution, $isCrossInstitution),
            'active_accreditation' => $activeAccreditation,
            'missing_foundation_data' => $this->missingFoundationData(
                $institution,
                $programs,
                $applicationCount,
                $isCrossInstitution,
            ),
        ];
    }

    /**
     * @return Collection<int, Program>
     */
    private function programsFor(User $user, ?Institution $institution, bool $isCrossInstitution)
    {
        if ($isCrossInstitution) {
            return Program::query()->orderBy('name')->get();
        }

        if ($institution === null) {
            return collect();
        }

        return $institution->programs()->orderBy('name')->get();
    }

    private function applicationCountFor(?Institution $institution, bool $isCrossInstitution): int
    {
        if ($isCrossInstitution) {
            return AccreditationApplication::query()->count();
        }

        if ($institution === null) {
            return 0;
        }

        return AccreditationApplication::query()
            ->forInstitution($institution->id)
            ->count();
    }

    private function openApplicationCount(?Institution $institution, bool $isCrossInstitution): int
    {
        $openStatuses = array_values(array_filter(
            array_map(
                fn (ApplicationStatus $status): ?string => $status->isOpen() ? $status->value : null,
                ApplicationStatus::cases(),
            ),
        ));

        $query = AccreditationApplication::query()->whereIn('status', $openStatuses);

        if (! $isCrossInstitution) {
            if ($institution === null) {
                return 0;
            }

            $query->forInstitution($institution->id);
        }

        return $query->count();
    }

    /**
     * Summarises whether any visible program currently holds accreditation.
     *
     * @param  Collection<int, Program>  $programs
     * @return array<string, mixed>
     */
    private function activeAccreditation($programs): array
    {
        $accredited = $programs->filter(fn (Program $p): bool => $p->hasActiveAccreditation());

        return [
            'is_active' => $accredited->isNotEmpty(),
            'accredited_program_count' => $accredited->count(),
            // Nearest expiry among currently accredited programs, if recorded.
            'next_expiry' => $accredited
                ->filter(fn (Program $p): bool => $p->accredited_until !== null)
                ->sortBy('accredited_until')
                ->first()?->accredited_until?->toDateString(),
        ];
    }

    /**
     * Foundation data an operator still needs to supply before the workflow
     * milestones become usable.
     *
     * @param  Collection<int, Program>  $programs
     * @return array<int, string>
     */
    private function missingFoundationData(
        ?Institution $institution,
        $programs,
        int $applicationCount,
        bool $isCrossInstitution,
    ): array {
        $missing = [];

        if (! $isCrossInstitution && $institution === null) {
            $missing[] = 'This account is not linked to an institution.';
        }

        if ($programs->isEmpty()) {
            $missing[] = 'No training program has been registered.';
        }

        if ($applicationCount === 0) {
            $missing[] = 'No accreditation application has been created.';
        }

        if (ChecklistRequirement::query()->where('is_active', true)->doesntExist()) {
            $missing[] = 'No active checklist requirements have been configured.';
        }

        if ($programs->isNotEmpty()
            && $programs->every(fn (Program $p): bool => ! $p->hasActiveAccreditation())) {
            $missing[] = 'No program currently holds an active accreditation decision.';
        }

        return $missing;
    }
}
