<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use AichaDigital\Laratickets\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller/middleware
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'department_id' => ['required', 'exists:departments,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'The ticket subject is required',
            'description.required' => 'The ticket description is required',
            'priority.required' => 'Please select a priority level',
            'department_id.required' => 'Please select a department',
            'department_id.exists' => 'The selected department does not exist',
        ];
    }
}
