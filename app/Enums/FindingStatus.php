<?php

namespace App\Enums;

use App\Services\Accreditation\FindingService;

/**
 * Finding lifecycle states for the Findings & Corrective-Action module.
 *
 * The transition matrix is enforced server-side in {@see FindingService}
 * (and the policy layer); the client never drives a transition.
 *
 * Allowed transitions:
 *   DRAFT                          -> OPEN
 *   OPEN                           -> ACKNOWLEDGED
 *   OPEN                           -> CORRECTIVE_ACTION_REQUIRED
 *   ACKNOWLEDGED                   -> CORRECTIVE_ACTION_REQUIRED
 *   CORRECTIVE_ACTION_REQUIRED     -> UNDER_REVIEW
 *   UNDER_REVIEW                   -> RESOLVED
 *   UNDER_REVIEW                   -> REOPENED
 *   RESOLVED                       -> CLOSED
 *   RESOLVED                       -> REOPENED
 *   OPEN                           -> WAIVED        (authorized PSP/CART only)
 *   Any non-closed finding         -> VOIDED        (authorized admin; reason required)
 *
 * Rules:
 *   - A finding cannot be CLOSED without an accepted corrective action or an
 *     authorized waiver.
 *   - REOPENED findings require a reason.
 *   - WAIVED requires an authorized user and a reason.
 *   - VOIDED findings remain visible in history.
 *   - Closed findings cannot be edited through ordinary actions.
 *   - Evidence upload never auto-closes a finding.
 */
enum FindingStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case CorrectiveActionRequired = 'corrective_action_required';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case Waived = 'waived';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::CorrectiveActionRequired => 'Corrective Action Required',
            self::UnderReview => 'Under Review',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
            self::Waived => 'Waived',
            self::Voided => 'Voided',
        };
    }

    /**
     * Allowed destination states from the current state. VOIDED and WAIVED are
     * handled specially by the service (any non-closed -> VOIDED; OPEN -> WAIVED).
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::Acknowledged, self::CorrectiveActionRequired, self::Waived],
            self::Acknowledged => [self::CorrectiveActionRequired],
            self::CorrectiveActionRequired => [self::UnderReview],
            self::UnderReview => [self::Resolved, self::Reopened],
            self::Resolved => [self::Closed, self::Reopened],
            self::Closed => [self::Reopened],
            self::Reopened => [self::CorrectiveActionRequired],
            self::Waived => [],
            self::Voided => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isVoided(): bool
    {
        return $this === self::Voided;
    }

    public function isWaived(): bool
    {
        return $this === self::Waived;
    }

    /**
     * Terminal states that cannot be reached again via ordinary transitions.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Voided], true);
    }

    /**
     * A finding is editable through ordinary actions only while not closed,
     * voided, or waived.
     */
    public function isEditable(): bool
    {
        return ! in_array($this, [self::Closed, self::Voided, self::Waived], true);
    }

    /**
     * Whether a reason is mandatory when (re)opening / reopening this finding.
     */
    public function requiresReopenReason(): bool
    {
        return $this === self::UnderReview || $this === self::Resolved;
    }
}
