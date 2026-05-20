<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketMessageRequest extends FormRequest
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
        $maxLength = (int) config('laratickets.messages.max_body_length', 5000);

        return [
            'body' => ['required', 'string', "max:$maxLength"],
            'author_role' => ['required', Rule::enum(MessageAuthorRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'The message body is required',
            'body.string' => 'The message body must be text',
            'author_role.required' => 'The author role is required',
            'author_role.enum' => 'The author role value is invalid',
        ];
    }
}
