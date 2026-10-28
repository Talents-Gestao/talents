<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientEvaluationRequest extends FormRequest
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
            'cycle_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'cycle_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'respondent_name' => ['required', 'string', 'max:120'],
            'respondent_email' => ['required', 'string', 'max:255', 'email'],
            'respondent_phone' => ['nullable', 'string', 'max:30'],
            'expires_at' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+180 days'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cycle_year' => 'ano',
            'cycle_quarter' => 'trimestre',
            'respondent_name' => 'nome do contato',
            'respondent_email' => 'e-mail',
            'respondent_phone' => 'celular',
            'expires_at' => 'validade',
        ];
    }
}
