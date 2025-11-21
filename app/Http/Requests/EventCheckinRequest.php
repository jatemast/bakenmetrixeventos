<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventCheckinRequest extends FormRequest
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
        return [
            'checkin_code' => 'required|string|exists:events,checkin_code',
            'cedula' => 'required|string|exists:personas,cedula',
            'referral_code' => 'nullable|string|exists:personas,referral_code',
        ];
    }
}
