<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedactTicketMessageRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A redaction reason is required',
            'reason.string' => 'The redaction reason must be text',
        ];
    }
}
