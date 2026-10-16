<?php

declare(strict_types=1);

namespace App\Http\Requests\ClientEvaluation;

use Illuminate\Foundation\Http\FormRequest;

class SubmitClientEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nps_score' => ['required', 'integer', 'min:0', 'max:10'],
            'service_score' => ['required', 'integer', 'min:1', 'max:5'],
            'delivery_score' => ['required', 'integer', 'min:1', 'max:5'],
            'communication_score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'answered_name' => ['required', 'string', 'max:120'],
            'answered_role' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nps_score' => 'recomendação',
            'service_score' => 'atendimento',
            'delivery_score' => 'qualidade da entrega',
            'communication_score' => 'comunicação',
            'comment' => 'comentário',
            'answered_name' => 'nome',
            'answered_role' => 'cargo',
        ];
    }
}
