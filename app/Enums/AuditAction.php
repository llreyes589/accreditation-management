<?php

namespace App\Enums;

/**
 * Action vocabulary for the append-only audit log.
 */
enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Viewed = 'viewed';
    case StatusChanged = 'status_changed';
    case RoleAssigned = 'role_assigned';
    case RoleRevoked = 'role_revoked';
    case ConfigurationChanged = 'configuration_changed';

    // Accreditation application wizard.
    case ApplicationCreated = 'application_created';
    case ApplicationStepSaved = 'application_step_saved';
    case ChecklistResponseChanged = 'checklist_response_changed';
    case DeclarationAccepted = 'declaration_accepted';
    case ApplicationSubmitted = 'application_submitted';
    case ApplicationWithdrawn = 'application_withdrawn';
    case ApplicationReturned = 'application_returned';
}
