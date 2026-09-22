<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => [
                'required',
                'integer',
                'exists:merchants,id',
            ],

            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'subscription_id' => [
                'required',
                'integer',
                'exists:subscriptions,id',
            ],

            'usage_date' => [
                'required',
                'date',
            ],

            'units' => [
                'required',
                'integer',
                'min:1',
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:128',
            ],
        ];
    }
}
