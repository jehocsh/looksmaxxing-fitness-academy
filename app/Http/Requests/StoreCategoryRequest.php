<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', Rule::unique('membership_categories')->ignore($this->route('category')?->id)], 'discount_percent' => ['required', 'integer', 'between:0,99'], 'is_active' => ['required', 'boolean']];
    }
}
