<?php

namespace App\Enums;

/**
 * Canonical role slugs seeded into the `roles` table.
 *
 * The set of roles is structural (see docs/security.md §3); the permissions
 * attached to each role are configurable data, not hard-coded here.
 */
enum RoleName: string
{
    case PspAdministrator = 'psp_administrator';
    case CartReviewer = 'cart_reviewer';
    case Inspector = 'inspector';
    case InstitutionAdministrator = 'institution_administrator';
    case TrainingOfficer = 'training_officer';
    case Consultant = 'consultant';
    case Resident = 'resident';
    case ReadOnlyAuditor = 'read_only_auditor';

    public function label(): string
    {
        return match ($this) {
            self::PspAdministrator => 'PSP Administrator',
            self::CartReviewer => 'CART Reviewer',
            self::Inspector => 'Inspector',
            self::InstitutionAdministrator => 'Institution Administrator',
            self::TrainingOfficer => 'Training Officer',
            self::Consultant => 'Consultant',
            self::Resident => 'Resident',
            self::ReadOnlyAuditor => 'Read-only Auditor',
        };
    }

    /**
     * Roles whose remit spans institutions rather than a single institution.
     *
     * Scope assignment remains configurable per user via `role_user.institution_id`;
     * this list only describes the default reach of the role.
     *
     * @return array<int, self>
     */
    public static function crossInstitutionRoles(): array
    {
        return [
            self::PspAdministrator,
            self::CartReviewer,
            self::Inspector,
            self::ReadOnlyAuditor,
        ];
    }

    public function isCrossInstitution(): bool
    {
        return in_array($this, self::crossInstitutionRoles(), true);
    }
}
