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

    /*
    |--------------------------------------------------------------------------
    | Supporting documents
    |--------------------------------------------------------------------------
    |
    | Limits for applicant-uploaded supporting documents. These are the single
    | source of truth: the upload validation rule and the storage service both
    | read from this block, so the limit is never hard-coded in multiple places.
    |
    | The default maximum is 20 MB per file (20 * 1024 * 1024 bytes). The list
    | of accepted formats is intentionally narrow for the MVP: PDF, Office
    | documents, and raster images only — no executables, archives, or markup.
    */

    'document' => [
        'max_size_bytes' => env('ACCREDITATION_DOCUMENT_MAX_SIZE', 20 * 1024 * 1024),

        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
            'image/jpeg',
            'image/png',
        ],

        'allowed_extensions' => ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png'],

        'max_filename_length' => 255,

        // Disk used for private storage. Keep this private (never the "public"
        // disk). Designed to swap to an S3-compatible private disk later.
        'disk' => env('ACCREDITATION_DOCUMENT_DISK', 'accreditation'),
    ],

];
