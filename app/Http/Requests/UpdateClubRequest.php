<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $club = $this->route('club');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clubs', 'name')
                    ->where('campus_id', $club?->campus_id)
                    ->ignore($club?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'founded_date' => ['nullable', 'date', 'before_or_equal:today'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'cover_url' => ['nullable', 'url', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:500'],
            'social_links.instagram' => ['nullable', 'url', 'max:500'],
            'social_links.twitter' => ['nullable', 'url', 'max:500'],
            'social_links.linkedin' => ['nullable', 'url', 'max:500'],
            'social_links.website' => ['nullable', 'url', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'achievements' => ['nullable', 'array'],
            'achievements.*' => ['string', 'max:500'],
            // 'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The club name is required.',
            'name.string' => 'The club name must be text.',
            'name.max' => 'The club name cannot exceed 255 characters.',
            'name.unique' => 'A club with this name already exists on this campus.',
            'description.string' => 'The description must be text.',
            'description.max' => 'The description cannot exceed 2000 characters.',
            'founded_date.date' => 'The founded date must be a valid date.',
            'founded_date.before_or_equal' => 'The founded date cannot be in the future.',
            'avatar_url.url' => 'The avatar URL must be a valid URL.',
            'avatar_url.max' => 'The avatar URL cannot exceed 500 characters.',
            'thumbnail_url.url' => 'The thumbnail URL must be a valid URL.',
            'thumbnail_url.max' => 'The thumbnail URL cannot exceed 500 characters.',
            'cover_url.url' => 'The cover URL must be a valid URL.',
            'cover_url.max' => 'The cover URL cannot exceed 500 characters.',
            'social_links.array' => 'Social links must be an object.',
            'social_links.facebook.url' => 'The Facebook URL must be a valid URL.',
            'social_links.facebook.max' => 'The Facebook URL cannot exceed 500 characters.',
            'social_links.instagram.url' => 'The Instagram URL must be a valid URL.',
            'social_links.instagram.max' => 'The Instagram URL cannot exceed 500 characters.',
            'social_links.twitter.url' => 'The Twitter URL must be a valid URL.',
            'social_links.twitter.max' => 'The Twitter URL cannot exceed 500 characters.',
            'social_links.linkedin.url' => 'The LinkedIn URL must be a valid URL.',
            'social_links.linkedin.max' => 'The LinkedIn URL cannot exceed 500 characters.',
            'social_links.website.url' => 'The website URL must be a valid URL.',
            'social_links.website.max' => 'The website URL cannot exceed 500 characters.',
            'contact_email.email' => 'The contact email must be a valid email address.',
            'contact_email.max' => 'The contact email cannot exceed 255 characters.',
            'contact_phone.string' => 'The contact phone must be text.',
            'contact_phone.max' => 'The contact phone cannot exceed 20 characters.',
            'achievements.array' => 'Achievements must be a list.',
            'achievements.*.string' => 'Each achievement must be text.',
            'achievements.*.max' => 'Each achievement cannot exceed 500 characters.',
            // 'status.required' => 'The status is required.',
            // 'status.string' => 'The status must be text.',
            // 'status.in' => 'The status must be either active or inactive.',
        ];
    }
}
