<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checklist version
    |--------------------------------------------------------------------------
    |
    | Every application records the checklist version in force when it was
    | created, so that later edits to the configurable checklist never change
    | the criteria an application was assessed against.
    |
    | Bump this whenever the seeded checklist content changes materially.
    |
    */

    'checklist_version' => env('ACCREDITATION_CHECKLIST_VERSION', '2026.1'),

    /*
    |--------------------------------------------------------------------------
    | Application reference
    |--------------------------------------------------------------------------
    |
    | Prefix used when generating human-readable application reference numbers.
    |
    */

    'reference_prefix' => env('ACCREDITATION_REFERENCE_PREFIX', 'PSP'),

];
