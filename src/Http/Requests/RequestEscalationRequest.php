<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestEscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_level_id' => ['required', 'exists:ticket_levels,id'],
            'justification' => ['required', 'string', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_level_id.required' => 'Please select a target level',
            'target_level_id.exists' => 'The selected level does not exist',
            'justification.required' => 'Please provide a justification for escalation',
            'justification.min' => 'Justification must be at least 10 characters',
        ];
    }
}
