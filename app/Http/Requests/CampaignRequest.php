<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampaignRequest extends FormRequest
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
            'name' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'target_citizen' => 'nullable|string|max:255',
            'special_observations' => 'nullable|string',
            'citizen_segmentation_file' => 'nullable|file|mimes:csv,pdf,xlsx,xls|max:2048',
            'leader_segmentation_file' => 'nullable|file|mimes:csv,pdf,xlsx,xls|max:2048',
            'militant_segmentation_file' => 'nullable|file|mimes:csv,pdf,xlsx,xls|max:2048',
            'requesting_dependency' => 'nullable|string|max:255',
            'campaign_manager' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'number_of_events' => 'nullable|integer|min:0',
            'campaign_number' => 'nullable|integer|unique:campaigns,campaign_number',
        ];
    }
}
