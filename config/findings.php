<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finding classifications
    |--------------------------------------------------------------------------
    |
    | Configurable vocabulary for finding classification. These are demonstration
    | values for the MVP and are NOT asserted to be official PSP classifications
    | unless already present in approved project configuration or official source
    | material. Severity on a finding is a snapshot of the inspection criterion's
    | severity at inspection time and is stored on the finding, not taken from
    | this list.
    |
    | The labels can be edited here without code changes; historical findings
    | store their own snapshot of the chosen category value.
    */

    'categories' => [
        'major' => 'Major',
        'minor' => 'Minor',
        'observation' => 'Observation',
        'opportunity_for_improvement' => 'Opportunity for Improvement',
    ],

    /*
    |--------------------------------------------------------------------------
    | Creation rules
    |--------------------------------------------------------------------------
    |
    | Whether findings may be drafted from an inspection that has been submitted
    | for review but not yet completed (per spec, governance may allow drafting
    | before final inspection completion). The criterion must always have a
    | recorded result.
    */

    'allow_before_completion' => env('FINDINGS_ALLOW_BEFORE_COMPLETION', true),

    /*
    |--------------------------------------------------------------------------
    | Notification extension point
    |--------------------------------------------------------------------------
    |
    | Notifications (email/SMS) are intentionally out of scope for this module.
    | This flag marks where a future notification subsystem would hook in.
    */

    'notifications_enabled' => false,

];
