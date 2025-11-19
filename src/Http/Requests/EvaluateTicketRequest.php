<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minScore = config('laratickets.evaluation.min_score', 1.0);
        $maxScore = config('laratickets.evaluation.max_score', 5.0);

        return [
            'score' => ['required', 'numeric', "min:{$minScore}", "max:{$maxScore}"],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.required' => 'Please provide a score',
            'score.min' => 'Score must be at least :min',
            'score.max' => 'Score cannot exceed :max',
        ];
    }
}
