<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use App\Exceptions\ChecklistVersionException;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle orchestration for checklist versions.
 *
 * Encapsulates the immutability rules (no edit/delete once PUBLISHED) and the
 * single-published-per-program-type constraint, all inside database
 * transactions so an interrupted publish/clone leaves no partial state.
 */
class ChecklistVersionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The currently published version for a program type, if any.
     */
    public function publishedVersionForProgramType(ProgramType $type): ?ChecklistVersion
    {
        return ChecklistVersion::query()
            ->published()
            ->forProgramType($type)
            ->latest('published_at')
            ->first();
    }

    /**
     * Create a draft version owned by the acting user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(User $user, array $attributes): ChecklistVersion
    {
        return DB::transaction(function () use ($user, $attributes): ChecklistVersion {
            $version = ChecklistVersion::create(array_merge($attributes, [
                'status' => ChecklistVersionStatus::Draft,
                'created_by' => $user->id,
            ]));

            $this->audit->log(
                AuditAction::ChecklistVersionCreated,
                $user,
                $version,
                null,
                ['version_code' => $version->version_code],
            );

            return $version->refresh();
        });
    }

    /**
     * The full set of reasons publishing is blocked, empty when publishable.
     *
     * @return array<int, string>
     */
    public function publishingErrors(ChecklistVersion $version): array
    {
        $errors = [];

        if (! $version->isEditable()) {
            $errors[] = 'Only a DRAFT version can be published.';
        }

        if ($version->sections()->where('is_active', true)->count() === 0) {
            $errors[] = 'A version must contain at least one active section.';
        }

        if ($version->sections()->where('is_active', true)->whereHas('requirements', fn ($q) => $q->where('checklist_requirements.is_active', true))->count() === 0) {
            $errors[] = 'A version must contain at least one active requirement.';
        }

        $invalidRequirements = $version->sections()
            ->whereHas('requirements', function ($q): void {
                $q->where('checklist_requirements.is_active', true)
                    ->where(function ($q2): void {
                        $q2->whereNull('code')
                            ->orWhere('code', '')
                            ->orWhereNull('requirement')
                            ->orWhere('requirement', '')
                            ->orWhereNull('checklist_section_id')
                            ->orWhereNull('severity');
                    });
            })
            ->exists();

        if ($invalidRequirements) {
            $errors[] = 'Every active requirement must have a code, title, section, and severity.';
        }

        return $errors;
    }

    /**
     * Publish a draft version after re-validating inside the transaction.
     */
    public function publish(User $user, ChecklistVersion $version, ?string $reason = null): ChecklistVersion
    {
        return DB::transaction(function () use ($user, $version, $reason): ChecklistVersion {
            /** @var ChecklistVersion $locked */
            $locked = ChecklistVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            $errors = $this->publishingErrors($locked);

            if ($errors !== []) {
                $this->audit->log(
                    AuditAction::ChecklistVersionPublishBlocked,
                    $user,
                    $locked,
                    null,
                    ['reasons' => $errors],
                );

                throw ChecklistVersionException::cannotPublish($errors);
            }

            // Enforce single-published-per-program-type: retire any other
            // published version for the same program type.
            ChecklistVersion::query()
                ->published()
                ->forProgramType($locked->program_type)
                ->whereKeyNot($locked->getKey())
                ->update([
                    'status' => ChecklistVersionStatus::Retired->value,
                    'retired_at' => now(),
                    'retired_by' => $user->id,
                    'retired_reason' => 'Superseded by publication of '.$locked->version_code.'.',
                ]);

            $locked->update([
                'status' => ChecklistVersionStatus::Published,
                'published_at' => now(),
                'published_by' => $user->id,
                'published_reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::ChecklistVersionPublished,
                $user,
                $locked,
                null,
                [
                    'version_code' => $locked->version_code,
                    'program_type' => $locked->program_type->value,
                    'reason' => $reason,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * Retire a published version; responses/requirements are untouched.
     */
    public function retire(User $user, ChecklistVersion $version, ?string $reason = null): ChecklistVersion
    {
        return DB::transaction(function () use ($user, $version, $reason): ChecklistVersion {
            /** @var ChecklistVersion $locked */
            $locked = ChecklistVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPublished()) {
                throw ChecklistVersionException::cannotRetire($locked->status->label());
            }

            $appCount = AccreditationApplication::query()
                ->where('checklist_version_id', $locked->id)
                ->count();

            $locked->update([
                'status' => ChecklistVersionStatus::Retired,
                'retired_at' => now(),
                'retired_by' => $user->id,
                'retired_reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::ChecklistVersionRetired,
                $user,
                $locked,
                null,
                [
                    'version_code' => $locked->version_code,
                    'reason' => $reason,
                    'applications_referencing' => $appCount,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * Delete an unused draft (never one used by any application).
     */
    public function deleteDraft(User $user, ChecklistVersion $version): void
    {
        DB::transaction(function () use ($user, $version): void {
            /** @var ChecklistVersion $locked */
            $locked = ChecklistVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw ChecklistVersionException::cannotDelete($locked->status->label());
            }

            $used = AccreditationApplication::query()
                ->where('checklist_version_id', $locked->id)
                ->exists();

            if ($used) {
                throw ChecklistVersionException::inUse();
            }

            $code = $locked->version_code;

            $locked->sections()->each(fn (ChecklistSection $s) => $s->requirements()->delete());
            $locked->sections()->delete();
            $locked->delete();

            $this->audit->log(
                AuditAction::ChecklistVersionDeleted,
                $user,
                $locked,
                null,
                ['version_code' => $code],
            );
        });
    }

    /**
     * Clone a version into a new DRAFT, copying sections and requirements.
     *
     * Runs inside a transaction; the source version is never modified.
     *
     * @param  array<string, mixed>  $overrides  e.g. version_code, name
     */
    public function clone(User $user, ChecklistVersion $source, array $overrides = []): ChecklistVersion
    {
        return DB::transaction(function () use ($user, $source, $overrides): ChecklistVersion {
            $clone = ChecklistVersion::create(array_merge([
                'version_code' => $overrides['version_code']
                    ?? 'COPY-'.strtoupper(substr(md5((string) $source->id.microtime(true)), 0, 8)),
                'name' => $overrides['name'] ?? $source->name.' (copy)',
                'description' => $source->description,
                'program_type' => $source->program_type->value,
                'status' => ChecklistVersionStatus::Draft,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => $user->id,
            ], $overrides));

            foreach ($source->sections()->orderBy('position')->get() as $section) {
                $newSection = ChecklistSection::create([
                    'checklist_version_id' => $clone->id,
                    'code' => $section->code,
                    'title' => $section->title,
                    'description' => $section->description,
                    'position' => $section->position,
                    'is_active' => $section->is_active,
                ]);

                foreach ($section->requirements()->orderBy('position')->get() as $requirement) {
                    ChecklistRequirement::create([
                        'checklist_version_id' => $clone->id,
                        'checklist_section_id' => $newSection->id,
                        'code' => $requirement->code,
                        'requirement' => $requirement->requirement,
                        'guidance' => $requirement->guidance,
                        'response_type' => $requirement->response_type->value,
                        'severity' => $requirement->severity->value,
                        'weight' => $requirement->weight,
                        'pass_threshold' => $requirement->pass_threshold,
                        'is_mandatory' => $requirement->is_mandatory,
                        'is_reviewer_only' => $requirement->is_reviewer_only,
                        'requires_document' => $requirement->requires_document,
                        'applicable_program_types' => $requirement->applicable_program_types,
                        'position' => $requirement->position,
                        'is_active' => $requirement->is_active,
                    ]);
                }
            }

            $this->audit->log(
                AuditAction::ChecklistVersionCloned,
                $user,
                $clone,
                null,
                [
                    'source_version_code' => $source->version_code,
                    'new_version_code' => $clone->version_code,
                ],
            );

            return $clone->refresh();
        });
    }
}
