<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side validation for a reviewer decision on a document.
 *
 * A comment is mandatory for reject, replacement-request, and return-to-review
 * decisions. The status-before check (is the document still reviewable?) is
 * enforced in the service layer and the policy, not here.
 */
class ReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('document')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(DocumentDecision::values())],
            'comment' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->requiresComment()),
            ],
        ];
    }

    /**
     * Map the requested decision to whether a comment is mandatory.
     */
    public function requiresComment(): bool
    {
        $decision = $this->input('decision');

        if (! is_string($decision)) {
            return false;
        }

        return DocumentDecision::tryFrom($decision)?->requiresComment() ?? false;
    }

    public function decision(): DocumentDecision
    {
        return DocumentDecision::from($this->string('decision')->value());
    }
}
