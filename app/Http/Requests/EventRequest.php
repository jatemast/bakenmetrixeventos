<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
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
            'campaign_id' => 'required|exists:campaigns,id',
            'detail' => 'required|string|max:255',
            'date' => 'required|date',
            'responsible' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'dynamic' => 'nullable|string',
            'ai_agent_info_file' => 'nullable|file|mimes:pdf|max:2048',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'checkin_code' => 'required|string|max:255|unique:events,checkin_code',
            'checkout_code' => 'required|string|max:255|unique:events,checkout_code',
            'bonus_points_for_attendee' => 'required|integer|min:0',
            'bonus_points_for_leader' => 'required|integer|min:0',
        ];
    }
}