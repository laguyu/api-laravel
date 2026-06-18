<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_address' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string'],
        ];
    }
}
