<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use AichaDigital\Laratickets\Enums\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'risk_level' => ['required', Rule::enum(RiskLevel::class)],
            'justification' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'risk_level.required' => 'Please select a risk level',
            'justification.required' => 'Please provide a justification for this risk assessment',
            'justification.min' => 'Justification must be at least 10 characters',
        ];
    }
}
