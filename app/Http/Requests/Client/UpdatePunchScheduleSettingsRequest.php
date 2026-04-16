<?php

namespace App\Http\Requests\Client;

use App\Services\Rhid\PunchScheduleSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePunchScheduleSettingsRequest extends FormRequest
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
        $time = ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'];
        $dayFields = [
            'entrada',
            'saida_almoco',
            'volta_almoco',
            'saida',
            'almoco2_inicio',
            'almoco2_fim',
            'trabalho2_entrada',
            'trabalho2_saida',
        ];

        $rules = [
            'segundo_trabalho' => ['required', 'boolean'],
            'segundo_almoco' => ['required', 'boolean'],
            'tolerancia_minutos' => ['nullable', 'integer', 'min:0', 'max:120'],
            'dias' => ['required', 'array'],
        ];

        foreach (PunchScheduleSettingsService::DAY_KEYS as $dayKey) {
            $rules["dias.{$dayKey}"] = ['required', 'array'];
            $rules["dias.{$dayKey}.ativo"] = ['required', 'boolean'];
            foreach ($dayFields as $f) {
                $rules["dias.{$dayKey}.{$f}"] = $time;
            }
        }

        return $rules;
    }
}
