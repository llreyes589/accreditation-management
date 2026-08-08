<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * Server-side validation for an applicant document upload.
 *
 * Every rule is derived from config('accreditation.document') so the limits
 * are enforced here exactly as they are in DocumentStorageService. We rely on
 * Laravel's uploaded-file validation plus an extension/MIME allow-list.
 */
class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Applicant upload rights are governed by the application's update
        // policy (institution membership + applicant role). Document-specific
        // actions (view/download/replace/withdraw) are enforced by DocumentPolicy.
        return $this->user()?->can('update', $this->route('application')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $config = Config::get('accreditation.document');

        return [
            'document' => [
                'required',
                'file',
                'max:'.(int) ($config['max_size_bytes'] / 1024), // kilobytes
                'mimes:'.implode(',', $config['allowed_extensions']),
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Please choose a file to upload.',
            'document.file' => 'The selected file is invalid.',
            'document.uploaded' => 'The file could not be uploaded. Check the file size and try again.',
            'document.mimes' => 'Only PDF, DOCX, XLSX, JPG, JPEG, and PNG files are allowed.',
            'document.max' => 'The file exceeds the maximum allowed size.',
        ];
    }
}
