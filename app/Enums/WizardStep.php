<?php

namespace App\Enums;

/**
 * The ordered steps of the accreditation application wizard.
 *
 * Step order and completion are derived from persisted application data, never
 * from session state.
 */
enum WizardStep: string
{
    case ProgramSelection = 'program_selection';
    case ApplicationDetails = 'application_details';
    case InstitutionInformation = 'institution_information';
    case LeadershipStaffing = 'leadership_staffing';
    case FacilitiesServices = 'facilities_services';
    case CurriculumTraining = 'curriculum_training';
    case ChecklistResponses = 'checklist_responses';
    case ReviewDeclaration = 'review_declaration';
    case SubmitApplication = 'submit_application';

    public function label(): string
    {
        return match ($this) {
            self::ProgramSelection => 'Program Selection',
            self::ApplicationDetails => 'Application Type and Program Details',
            self::InstitutionInformation => 'Institution Information',
            self::LeadershipStaffing => 'Program Leadership and Staffing',
            self::FacilitiesServices => 'Facilities and Laboratory Services',
            self::CurriculumTraining => 'Curriculum and Training Activities',
            self::ChecklistResponses => 'Checklist Responses',
            self::ReviewDeclaration => 'Review and Declaration',
            self::SubmitApplication => 'Save Draft or Submit',
        };
    }

    /**
     * One-based position of this step in the wizard.
     */
    public function position(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public static function first(): self
    {
        return self::ProgramSelection;
    }

    public static function last(): self
    {
        return self::SubmitApplication;
    }

    public function next(): ?self
    {
        return self::cases()[$this->position()] ?? null;
    }

    public function previous(): ?self
    {
        return $this->position() >= 2
            ? self::cases()[$this->position() - 2]
            : null;
    }

    /**
     * Steps whose completion counts toward the progress percentage.
     *
     * The final step is the submit action itself and carries no data of its
     * own, so it is excluded from the completion calculation.
     *
     * @return array<int, self>
     */
    public static function completableSteps(): array
    {
        $steps = [];

        foreach (self::cases() as $step) {
            if ($step !== self::SubmitApplication) {
                $steps[] = $step;
            }
        }

        return $steps;
    }

    /**
     * The JSON payload column backing this step, if it has one.
     */
    public function payloadColumn(): ?string
    {
        return match ($this) {
            self::ApplicationDetails => 'program_details',
            self::InstitutionInformation => 'institution_information',
            self::LeadershipStaffing => 'leadership_staffing',
            self::FacilitiesServices => 'facilities_services',
            self::CurriculumTraining => 'curriculum_training',
            default => null,
        };
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::first();
    }
}
