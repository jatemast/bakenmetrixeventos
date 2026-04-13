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
            'event_type_id' => 'nullable|exists:event_types,id',
            'detail' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'nullable|date_format:H:i',
            'duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'max_capacity' => 'nullable|integer|min:1',
            'target_universes' => 'nullable|array',
            'status' => 'nullable|in:scheduled,active,completed,cancelled',
            'responsible' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'dynamic' => 'nullable|string',
            'pdf_path' => 'sometimes|file|mimes:pdf,docx,doc,txt|max:20480',
            'ai_agent_info_file' => 'sometimes|file|mimes:pdf,docx,doc,txt|max:20480',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'municipality' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'bonus_points_for_attendee' => 'required|integer|min:0',
            'bonus_points_for_leader' => 'required|integer|min:0',
            'form_schema' => 'nullable|array',
            'success_message' => 'nullable|string',
            'slot_unit_name' => 'nullable|string|max:50',
            'grace_period_hours' => 'nullable|integer|min:1|max:24',
        ];
    }

    /**
     * Configure the validator instance.
     * Validate that event date falls within the campaign's date range.
     * Auto-calculate the status based on event date if not provided.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('campaign_id') && $this->has('date') && !$validator->errors()->has('date')) {
                $campaign = \App\Models\Campaign::find($this->campaign_id);
                if ($campaign) {
                    $eventDate = \Carbon\Carbon::parse($this->date);
                    
                    if ($campaign->start_date && $eventDate->lt(\Carbon\Carbon::parse($campaign->start_date)->startOfDay())) {
                        $validator->errors()->add('date', 
                            "La fecha del evento no puede ser anterior al inicio de la campaña ({$campaign->start_date->format('d/m/Y')})."
                        );
                    }
                    if ($campaign->end_date && $eventDate->gt(\Carbon\Carbon::parse($campaign->end_date)->endOfDay())) {
                        $validator->errors()->add('date', 
                            "La fecha del evento no puede ser posterior al fin de la campaña ({$campaign->end_date->format('d/m/Y')})."
                        );
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     * Decode JSON strings before validation (supports multipart form data)
     */
    protected function prepareForValidation()
    {
        // Decode target_universes if sent as JSON string
        if ($this->has('target_universes') && is_string($this->target_universes)) {
            $decoded = json_decode($this->target_universes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'target_universes' => $decoded
                ]);
            }
        }

        // Decode form_schema if sent as JSON string
        if ($this->has('form_schema') && is_string($this->form_schema)) {
            $decoded = json_decode($this->form_schema, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([
                    'form_schema' => $decoded
                ]);
            }
        }
    }
}