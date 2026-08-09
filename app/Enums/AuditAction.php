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

    // Application completeness review (administrative & documentary check only).
    case CompletenessReviewStarted = 'completeness_review_started';
    case CompletenessReviewReturned = 'completeness_review_returned';
    case CompletenessReviewComplete = 'completeness_review_complete';
    case ApplicationResubmitted = 'application_resubmitted';

    // Configurable checklist administration.
    case ChecklistVersionCreated = 'checklist_version_created';
    case ChecklistVersionCloned = 'checklist_version_cloned';
    case ChecklistVersionPublished = 'checklist_version_published';
    case ChecklistVersionRetired = 'checklist_version_retired';
    case ChecklistVersionDeleted = 'checklist_version_deleted';
    case ChecklistSectionCreated = 'checklist_section_created';
    case ChecklistSectionUpdated = 'checklist_section_updated';
    case ChecklistSectionReordered = 'checklist_section_reordered';
    case ChecklistSectionDeactivated = 'checklist_section_deactivated';
    case ChecklistRequirementCreated = 'checklist_requirement_created';
    case ChecklistRequirementUpdated = 'checklist_requirement_updated';
    case ChecklistRequirementReordered = 'checklist_requirement_reordered';
    case ChecklistRequirementDeactivated = 'checklist_requirement_deactivated';
    case ChecklistVersionPublishBlocked = 'checklist_version_publish_blocked';

    // Inspection scheduling & checklist.
    case InspectionCreated = 'inspection_created';
    case InspectionStarted = 'inspection_started';
    case InspectionSubmitted = 'inspection_submitted';
    case InspectionReturned = 'inspection_returned';
    case InspectionCompleted = 'inspection_completed';
    case InspectionCancelled = 'inspection_cancelled';
    case InspectionRescheduled = 'inspection_rescheduled';
    case CriterionResultChanged = 'criterion_result_changed';
    case EvidenceLinked = 'evidence_linked';
    case EvidenceUnlinked = 'evidence_unlinked';
    case InspectorAssigned = 'inspector_assigned';
    case InspectorRemoved = 'inspector_removed';
    case DocumentUploaded = 'document_uploaded';
    case DocumentReplacementUploaded = 'document_replacement_uploaded';
    case DocumentReviewStarted = 'document_review_started';
    case DocumentAccepted = 'document_accepted';
    case DocumentRejected = 'document_rejected';
    case DocumentReplacementRequested = 'document_replacement_requested';
    case DocumentReturnedToReview = 'document_returned_to_review';
    case DocumentWithdrawn = 'document_withdrawn';
    case DocumentVersionSuperseded = 'document_version_superseded';
    case DocumentDownloaded = 'document_downloaded';
}
