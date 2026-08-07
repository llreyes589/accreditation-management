<?php

namespace App\Http\Requests\Accreditation;

use App\Enums\ApplicationType;
use App\Models\AccreditationApplication;
use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Server-side validation for starting a new draft application.
 *
 * The program must belong to an institution the user is a member of: this is
 * the authoritative guard against selecting another institution's program.
 */
class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AccreditationApplication::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'application_type' => ['required', 'string', Rule::in(ApplicationType::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $programId = $this->input('program_id');

            if (! $programId) {
                return;
            }

            /** @var Program|null $program */
            $program = Program::query()->find($programId);
            $user = $this->user();

            if ($program === null || $user === null) {
                return;
            }

            if (! $user->belongsToInstitution($program->institution_id)) {
                $validator->errors()->add(
                    'program_id',
                    'The selected program does not belong to your institution.',
                );

                return;
            }

            if (! $program->status->isSelectableForApplication()) {
                $validator->errors()->add(
                    'program_id',
                    'The selected program is not active and cannot be used for an application.',
                );
            }
        });
    }

    public function programOrFail(): Program
    {
        return Program::findOrFail($this->integer('program_id'));
    }

    public function applicationType(): ApplicationType
    {
        return ApplicationType::from($this->string('application_type')->value());
    }
}
