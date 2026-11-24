<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('members')->ignore($this->route('member')?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'membership_category_id' => ['required', Rule::exists('membership_categories', 'id')->where('is_active', true)],
            'membership_level_id' => ['required', Rule::exists('membership_levels', 'id')->where('is_active', true)],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'status' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'is_deactivated' => ['prohibited'],
            'membership_expires_at' => ['prohibited'],
            'discount_percent' => ['prohibited'],
            'total_cents' => ['prohibited'],
        ];
    }
}
