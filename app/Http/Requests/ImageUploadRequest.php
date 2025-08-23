<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ImageUploadRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $context = $this->input('context', 'general');
        $contextConfig = config("uploads.contexts.{$context}", config('uploads.defaults'));

        return [
            'file' => [
                'required',
                'file',
                File::types($contextConfig['allowed_extensions'])
                    ->max($contextConfig['max_size']),
                'mimes:' . implode(',', $this->getExtensionsFromMimeTypes($contextConfig['allowed_types'])),
                'mimetypes:' . implode(',', $contextConfig['allowed_types']),
                function ($attribute, $value, $fail) use ($contextConfig) {
                    // Custom validation for file signature
                    if (config('uploads.security.validate_file_signature') && !$this->validateFileSignature($value, $contextConfig['allowed_types'])) {
                        $fail('The file signature does not match the file extension.');
                    }
                },
            ],
            'context' => [
                'required',
                'string',
                Rule::in(array_keys(config('uploads.contexts'))),
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
        $maxSizeMB = round($contextConfig['max_size'] / 1024, 2);

        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => "The file size cannot exceed {$maxSizeMB}MB for {$context} uploads.",
            'file.mimes' => 'The file must be one of the following types: ' . implode(', ', $contextConfig['allowed_extensions']) . '.',
            'file.mimetypes' => 'The file MIME type is not allowed for this context.',
            'context.required' => 'Upload context is required.',
            'context.in' => 'Invalid upload context. Allowed contexts: ' . implode(', ', array_keys(config('uploads.contexts'))) . '.',
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
            'file' => 'image file',
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
        if (!$this->has('context')) {
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

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
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
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  array  $allowedMimeTypes
     * @return bool
     */
    protected function validateFileSignature($file, array $allowedMimeTypes): bool
    {
        if (!$file || !$file->isValid()) {
            return false;
        }

        // Get file signature (magic bytes)
        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
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
                "GIF87a", // GIF87a
                "GIF89a", // GIF89a
            ],
            'image/webp' => [
                "RIFF", // WebP (first 4 bytes, followed by file size, then "WEBP")
            ],
            'image/svg+xml' => [
                "<?xml", // SVG
                "<svg", // SVG without XML declaration
            ],
        ];

        // Check if file signature matches any allowed MIME type
        foreach ($allowedMimeTypes as $mimeType) {
            if (!isset($signatures[$mimeType])) {
                continue;
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
        }

        return false;
    }

    /**
     * Get file extensions from MIME types.
     *
     * @param  array  $mimeTypes
     * @return array
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
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    protected function validateContextSpecificRules($validator): void
    {
        $context = $this->input('context');
        $file = $this->file('file');

        if (!$file || !$file->isValid()) {
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
     * @param  \Illuminate\Validation\Validator  $validator
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return void
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
     * @param  \Illuminate\Validation\Validator  $validator
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return void
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
     * @param  \Illuminate\Validation\Validator  $validator
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return void
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
     * Get the context configuration for the current request.
     *
     * @return array
     */
    public function getContextConfig(): array
    {
        $context = $this->input('context', 'general');
        return config("uploads.contexts.{$context}", config('uploads.defaults'));
    }

    /**
     * Check if the current context allows public access.
     *
     * @return bool
     */
    public function isPublicContext(): bool
    {
        $config = $this->getContextConfig();
        return $config['public'] ?? true;
    }

    /**
     * Get the storage disk for the current context.
     *
     * @return string
     */
    public function getStorageDisk(): string
    {
        $config = $this->getContextConfig();
        return $config['disk'] ?? 'images';
    }
}
