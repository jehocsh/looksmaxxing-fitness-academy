<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return ['submission_token' => ['required', 'uuid'], 'amount' => ['required', 'regex:/^\\d{1,6}(\\.\\d{1,2})?$/', 'numeric', 'min:0.01'], 'method' => ['required', Rule::in(['cash', 'gcash', 'bank_transfer'])], 'external_reference' => ['nullable', 'required_unless:method,cash', 'string', 'max:100']];
    }
}
