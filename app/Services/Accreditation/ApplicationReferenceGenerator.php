<?php

namespace App\Services\Accreditation;

use App\Models\AccreditationApplication;
use App\Models\Program;
use Illuminate\Support\Str;

/**
 * Generates human-readable, unique application reference numbers.
 *
 * Format: PSP-<institutionCode>-<year>-<random>
 * Uniqueness is additionally enforced by a unique index on the column.
 */
class ApplicationReferenceGenerator
{
    private const MAX_ATTEMPTS = 10;

    public function generate(Program $program): string
    {
        $prefix = config('accreditation.reference_prefix', 'PSP');
        $institutionCode = Str::of($program->institution->code ?? 'INST')
            ->replaceMatches('/[^A-Za-z0-9]/', '')
            ->upper()
            ->limit(8, '')
            ->value();

        $year = now()->format('Y');

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s-%s',
                $prefix,
                $institutionCode,
                $year,
                strtoupper(Str::random(6)),
            );

            if (! AccreditationApplication::where('application_reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Fall back to a value that cannot realistically collide.
        return sprintf('%s-%s-%s-%s', $prefix, $institutionCode, $year, strtoupper(Str::uuid()->toString()));
    }
}
