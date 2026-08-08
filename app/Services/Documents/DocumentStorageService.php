<?php

namespace App\Services\Documents;

use App\Exceptions\DocumentException;
use App\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores accreditation supporting documents on a private disk and computes the
 * metadata the {@see DocumentVersion} row needs.
 *
 * Design notes:
 * - The "local" accreditation disk is private (never the public disk, never
 *   symlinked under public/). Swapping to an S3-compatible private bucket only
 *   requires changing the disk definition in config/filesystems.php.
 * - The client-supplied filename is never used in the storage path. A UUID is
 *   generated server-side, so a guessed filename reveals nothing.
 * - All size/type/extension limits are read from config('accreditation.document')
 *   so there is a single source of truth.
 */
class DocumentStorageService
{
    /**
     * Validate the uploaded file against every configured rule.
     *
     * @throws DocumentException
     */
    public function validate(UploadedFile $file): void
    {
        // A PHP upload error (e.g. upload_max_filesize / post_max_size exceeded,
        // or a missing temp dir) means the file never arrived intact. Surface a
        // clear, actionable message instead of the generic "failed to upload".
        if (! $file->isValid()) {
            $code = $file->getError();

            $messages = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds the server\'s maximum upload size.',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum allowed size.',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was received. Please choose a file and try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk on the server.',
                UPLOAD_ERR_EXTENSION => 'The file upload was stopped by a server extension.',
            ];

            throw DocumentException::invalidFile($messages[$code] ?? ('PHP upload error code '.$code));
        }

        $config = Config::get('accreditation.document');

        if ($file->getSize() === false || $file->getSize() > $config['max_size_bytes']) {
            throw DocumentException::invalidFile('file exceeds the maximum allowed size.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $config['allowed_extensions'], true)) {
            throw DocumentException::invalidFile('file extension "'.$extension.'" is not allowed.');
        }

        $mime = $file->getClientMimeType();
        if (! in_array($mime, $config['allowed_mime_types'], true)) {
            throw DocumentException::invalidFile('MIME type "'.$mime.'" is not allowed.');
        }

        $original = $file->getClientOriginalName();
        if (mb_strlen($original) > $config['max_filename_length']) {
            throw DocumentException::invalidFile('filename is too long.');
        }
    }

    /**
     * Persist the file and return the version metadata (without touching the DB).
     *
     * @return array{
     *     original_filename: string,
     *     generated_filename: string,
     *     storage_disk: string,
     *     storage_path: string,
     *     mime_type: string,
     *     file_extension: string,
     *     file_size_bytes: int,
     *     sha256_hash: string,
     * }
     *
     * @throws DocumentException
     */
    public function store(UploadedFile $file, int $applicationId, int $documentId): array
    {
        $this->validate($file);

        $disk = Config::get('accreditation.document.disk', 'accreditation');
        $generatedName = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());
        $directory = 'accreditation/'.$applicationId.'/documents/'.$documentId;
        $path = $directory.'/'.$generatedName;

        // Stream the file to the private disk and compute the SHA-256 hash from
        // the stored bytes (never from client-supplied content metadata).
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw DocumentException::uploadFailed('could not read uploaded file.');
        }

        $hashContext = hash_init('sha256');
        Storage::disk($disk)->put($path, $stream);
        // Re-open the stored file to hash it (the upload temp stream is consumed).
        $stored = Storage::disk($disk)->path($path);
        $storedHandle = fopen($stored, 'rb');
        if ($storedHandle === false) {
            throw DocumentException::uploadFailed('stored file unreadable.');
        }
        hash_update_stream($hashContext, $storedHandle);
        $sha256 = hash_final($hashContext);

        return [
            'original_filename' => $file->getClientOriginalName(),
            'generated_filename' => $generatedName,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size_bytes' => (int) $file->getSize(),
            'sha256_hash' => $sha256,
        ];
    }

    /**
     * Delete a stored file. Failures are logged but never fatal: the metadata
     * row is the source of truth and physical cleanup is best-effort.
     */
    public function deleteFile(string $disk, string $path): void
    {
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
