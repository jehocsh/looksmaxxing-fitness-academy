<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', Rule::unique('membership_levels')->ignore($this->route('level')?->id)], 'fee' => ['required', 'regex:/^\\d{1,6}(\\.\\d{1,2})?$/', 'numeric', 'min:0.01'], 'validity_days' => ['required', 'integer', 'between:1,3660'], 'benefits_text' => ['required', 'string', 'max:2000'], 'is_active' => ['required', 'boolean']];
    }
}
