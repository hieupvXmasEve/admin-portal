<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Requests\Upload;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UploadRequest extends FormRequest
{
    /**
     * Holds a detailed upload error message when PHP reports a failure.
     */
    protected ?string $uploadErrorMessage = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Basic authorization - can be extended based on requirements
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $context = $this->input('context', 'general');
        $contextConfig = config("uploads.contexts.{$context}", config('uploads.defaults'));
        $maxSizeKilobytes = (int) ($contextConfig['max_size'] ?? config('uploads.defaults.max_size', 10240));
        $allowedExtensions = array_values(array_unique($contextConfig['allowed_extensions'] ?? []));
        $allowedMimeTypes = array_values(array_unique($contextConfig['allowed_types'] ?? []));

        if (empty($allowedExtensions)) {
            $allowedExtensions = array_values(array_unique(config('uploads.defaults.allowed_extensions', [])));
        }

        if (empty($allowedMimeTypes)) {
            $allowedMimeTypes = array_values(array_unique(config('uploads.defaults.allowed_types', [])));
        }

        $fileRules = [
            'required',
            'file',
            function ($attribute, $value, $fail) {
                if (! $value || ! ($value instanceof UploadedFile)) {
                    return;
                }

                if (! $value->isValid()) {
                    $message = $this->resolveUploadErrorMessage($value);
                    $this->uploadErrorMessage = $message;
                    $fail($message);
                }
            },
            File::types($allowedExtensions)->max($maxSizeKilobytes),
        ];

        if (! empty($allowedExtensions)) {
            $fileRules[] = 'mimes:'.implode(',', $allowedExtensions);
        }

        if (! empty($allowedMimeTypes)) {
            $fileRules[] = 'mimetypes:'.implode(',', $allowedMimeTypes);
        }

        $fileRules[] = function ($attribute, $value, $fail) {
            if (! config('uploads.security.validate_file_signature')) {
                return;
            }

            if (! $value) {
                return;
            }

            if (! $this->validateFileSignature($value)) {
                $fail('The file signature does not match the file extension.');
            }
        };

        return [
            'file' => $fileRules,
            'context' => [
                'required',
                'string',
                Rule::in($this->publicContexts()),
            ],
            'alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $context = $this->input('context', 'general');
        $contextConfig = config("uploads.contexts.{$context}", config('uploads.defaults'));
        $maxSizeKilobytes = (int) ($contextConfig['max_size'] ?? config('uploads.defaults.max_size', 10240));
        $maxSizeMB = round($maxSizeKilobytes / 1024, 2);
        $allowedExtensions = array_values(array_unique($contextConfig['allowed_extensions'] ?? []));
        if (empty($allowedExtensions)) {
            $allowedExtensions = array_values(array_unique(config('uploads.defaults.allowed_extensions', [])));
        }

        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => "The file size cannot exceed {$maxSizeMB}MB for {$context} uploads.",
            'file.mimes' => 'The file must be one of the following types: '.implode(', ', $allowedExtensions).'.',
            'file.mimetypes' => 'The file MIME type is not allowed for this context.',
            'file.uploaded' => $this->getUploadFailureMessage(),
            'context.required' => 'Upload context is required.',
            'context.in' => 'Invalid upload context. Allowed contexts: '.implode(', ', $this->publicContexts()).'.',
            'alt_text.max' => 'Alt text cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'expires_at.date' => 'Expiration date must be a valid date.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'uploaded file',
            'context' => 'upload context',
            'alt_text' => 'alternative text',
            'expires_at' => 'expiration date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default context if not provided
        if (! $this->has('context')) {
            $this->merge(['context' => 'general']);
        }

        // Sanitize alt_text and description
        if ($this->has('alt_text')) {
            $this->merge(['alt_text' => strip_tags($this->input('alt_text'))]);
        }

        if ($this->has('description')) {
            $this->merge(['description' => strip_tags($this->input('description'))]);
        }
    }

    /** @return list<string> */
    private function publicContexts(): array
    {
        return array_keys(array_filter(
            config('uploads.contexts'),
            static fn (array $config): bool => ! ($config['internal'] ?? false),
        ));
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional context-specific validation
            $this->validateContextSpecificRules($validator);
        });
    }

    /**
     * Validate file signature against allowed MIME types.
     *
     * @param  UploadedFile  $file
     * @param  array  $allowedMimeTypes
     */
    protected function validateFileSignature($file): bool
    {
        if (! $file || ! $file->isValid()) {
            return false;
        }

        // Get file signature (magic bytes)
        $handle = fopen($file->getPathname(), 'rb');
        if (! $handle) {
            return false;
        }

        $signature = fread($handle, 12); // Read first 12 bytes
        fclose($handle);

        // Define file signatures for common image types
        $signatures = [
            'image/jpeg' => [
                "\xFF\xD8\xFF", // JPEG
            ],
            'image/png' => [
                "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
            ],
            'image/gif' => [
                'GIF87a', // GIF87a
                'GIF89a', // GIF89a
            ],
            'image/webp' => [
                'RIFF', // WebP (first 4 bytes, followed by file size, then "WEBP")
            ],
            'image/svg+xml' => [
                '<?xml', // SVG
                '<svg', // SVG without XML declaration
            ],
        ];

        $mimeType = $file->getMimeType();

        if (! $mimeType || ! isset($signatures[$mimeType])) {
            // No signature validation available for this type
            return true;
        }

        foreach ($signatures[$mimeType] as $sig) {
            if (strpos($signature, $sig) === 0) {
                return true;
            }

            // Special case for WebP
            if ($mimeType === 'image/webp' && strpos($signature, 'RIFF') === 0) {
                $webpSignature = substr($signature, 8, 4);
                if ($webpSignature === 'WEBP') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get file extensions from MIME types.
     */
    protected function getExtensionsFromMimeTypes(array $mimeTypes): array
    {
        $mimeToExtension = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/svg+xml' => ['svg'],
        ];

        $extensions = [];
        foreach ($mimeTypes as $mimeType) {
            if (isset($mimeToExtension[$mimeType])) {
                $extensions = array_merge($extensions, $mimeToExtension[$mimeType]);
            }
        }

        return array_unique($extensions);
    }

    /**
     * Perform context-specific validation.
     *
     * @param  Validator  $validator
     */
    protected function validateContextSpecificRules($validator): void
    {
        $context = $this->input('context');
        $file = $this->file('file');

        if (! $file || ! $file->isValid()) {
            return;
        }

        switch ($context) {
            case 'avatar':
                $this->validateAvatarRules($validator, $file);
                break;
            case 'assignment':
                $this->validateAssignmentRules($validator, $file);
                break;
            case 'general':
                $this->validateGeneralRules($validator, $file);
                break;
        }
    }

    /**
     * Validate avatar-specific rules.
     *
     * @param  Validator  $validator
     * @param  UploadedFile  $file
     */
    protected function validateAvatarRules($validator, $file): void
    {
        // Avatar should be square or close to square
        if ($file->getMimeType() !== 'image/svg+xml') {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo) {
                [$width, $height] = $imageInfo;
                $ratio = $width / $height;

                // Allow aspect ratio between 0.8 and 1.25 (close to square)
                if ($ratio < 0.8 || $ratio > 1.25) {
                    $validator->errors()->add('file', 'Avatar images should be square or close to square (aspect ratio between 0.8 and 1.25).');
                }

                // Minimum dimensions for avatars
                if ($width < 100 || $height < 100) {
                    $validator->errors()->add('file', 'Avatar images must be at least 100x100 pixels.');
                }
            }
        }
    }

    /**
     * Validate assignment-specific rules.
     *
     * @param  Validator  $validator
     * @param  UploadedFile  $file
     */
    protected function validateAssignmentRules($validator, $file): void
    {
        // Assignment images should have reasonable dimensions
        if ($file->getMimeType() !== 'image/svg+xml') {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo) {
                [$width, $height] = $imageInfo;

                // Maximum dimensions for assignment images
                if ($width > 4000 || $height > 4000) {
                    $validator->errors()->add('file', 'Assignment images cannot exceed 4000x4000 pixels.');
                }

                // Minimum dimensions for assignment images
                if ($width < 200 || $height < 200) {
                    $validator->errors()->add('file', 'Assignment images must be at least 200x200 pixels.');
                }
            }
        }
    }

    /**
     * Validate general upload rules.
     *
     * @param  Validator  $validator
     * @param  UploadedFile  $file
     */
    protected function validateGeneralRules($validator, $file): void
    {
        // General validation rules for all other contexts
        if ($file->getMimeType() !== 'image/svg+xml') {
            $imageInfo = getimagesize($file->getPathname());
            if ($imageInfo) {
                [$width, $height] = $imageInfo;

                // Maximum dimensions for general images
                if ($width > 5000 || $height > 5000) {
                    $validator->errors()->add('file', 'Images cannot exceed 5000x5000 pixels.');
                }
            }
        }
    }

    /**
     * Translate PHP upload error codes into human readable messages.
     */
    protected function resolveUploadErrorMessage(UploadedFile $file): string
    {
        $errorCode = $file->getError();

        if ($errorCode === UPLOAD_ERR_OK) {
            return $file->getErrorMessage();
        }

        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');

        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => "The file is larger than the server allows (upload_max_filesize = {$uploadMax}).",
            UPLOAD_ERR_FORM_SIZE => 'The file exceeds the maximum size specified by the form.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded. Please select a file and try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server. Contact support.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk. Please try again later.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload. Contact support.',
            default => "The file could not be uploaded (server limits: upload_max_filesize {$uploadMax}, post_max_size {$postMax}).",
        };
    }

    /**
     * Resolve a friendly message for upload failures.
     */
    protected function getUploadFailureMessage(): string
    {
        if ($this->uploadErrorMessage) {
            return $this->uploadErrorMessage;
        }

        $file = $this->file('file');
        if ($file instanceof UploadedFile && ! $file->isValid()) {
            return $this->resolveUploadErrorMessage($file);
        }

        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');

        return "The file could not be uploaded (server limits: upload_max_filesize {$uploadMax}, post_max_size {$postMax}).";
    }

    /**
     * Get the context configuration for the current request.
     */
    public function getContextConfig(): array
    {
        $context = $this->input('context', 'general');

        return config("uploads.contexts.{$context}", config('uploads.defaults'));
    }

    /**
     * Check if the current context allows public access.
     */
    public function isPublicContext(): bool
    {
        $config = $this->getContextConfig();

        return $config['public'] ?? true;
    }

    /**
     * Get the storage disk for the current context.
     */
    public function getStorageDisk(): string
    {
        $config = $this->getContextConfig();

        return $config['disk'] ?? 'images';
    }
}
