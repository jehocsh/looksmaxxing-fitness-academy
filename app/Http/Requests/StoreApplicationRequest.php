<?php

namespace App\Http\Requests;

class StoreApplicationRequest extends StoreMemberRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [...parent::rules(), 'consent' => ['accepted']];
    }
}
