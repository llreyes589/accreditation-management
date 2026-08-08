<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => AccreditationApplication::factory(),
            'application_requirement_id' => null,
            'document_type' => DocumentType::RequirementDocument,
            'status' => DocumentStatus::Uploaded,
            'created_by' => User::factory(),
            'current_version_id' => null,
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::Accepted]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::Rejected]);
    }

    public function replacementRequested(): self
    {
        return $this->state(fn (): array => ['status' => DocumentStatus::ReplacementRequested]);
    }

    public function forRequirement(ApplicationRequirement $requirement): self
    {
        return $this->state(fn (): array => [
            'application_id' => $requirement->accreditation_application_id,
            'application_requirement_id' => $requirement->getKey(),
        ]);
    }
}
