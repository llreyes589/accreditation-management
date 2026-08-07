<?php

namespace App\Enums;

/**
 * Action vocabulary for the append-only audit log.
 *
 * Only actions reachable in the foundation milestone are listed; further
 * actions are added as their workflows are implemented.
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
}
