<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cadastro técnico da campanha. Todo campo é opcional; vazio é lacuna e grava nulo.
 */
class UpdateSurveyCampaignRegistrationRequest extends FormRequest
{
    public const SHORT_TEXT_FIELDS = [
        'unit_name',
        'process_name',
        'legal_name_snapshot',
        'technical_responsible_name',
        'technical_responsible_role',
        'approving_organization_name',
    ];

    public const LONG_TEXT_FIELDS = [
        'eligible_source',
        'aep_summary',
        'observation_summary',
        'existing_controls',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            array_fill_keys(self::SHORT_TEXT_FIELDS, ['nullable', 'string', 'max:255']),
            array_fill_keys(self::LONG_TEXT_FIELDS, ['nullable', 'string', 'max:10000']),
            [
                'cnpj_snapshot' => ['nullable', 'string', 'max:20'],
                'technical_responsible_registration' => ['nullable', 'string', 'max:64'],
                'eligible_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'exposure_groups' => ['nullable', 'array', 'max:100'],
                'exposure_groups.*' => ['nullable', 'string', 'max:255'],
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'eligible_count.integer' => 'O total de elegíveis precisa ser um número inteiro.',
            'eligible_count.min' => 'O total de elegíveis não pode ser negativo.',
        ];
    }

    /**
     * Campos prontos para gravar: texto só com espaços vira nulo, elegíveis vazio fica nulo
     * (0 só quando digitado) e grupos de exposição viram lista de textos não vazios ou nulo.
     *
     * @return array<string, mixed>
     */
    public function registrationData(): array
    {
        $validated = $this->validated();
        $textFields = array_merge(
            self::SHORT_TEXT_FIELDS,
            self::LONG_TEXT_FIELDS,
            ['cnpj_snapshot', 'technical_responsible_registration'],
        );

        $data = [];
        foreach ($textFields as $field) {
            $value = trim((string) ($validated[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        $eligible = $validated['eligible_count'] ?? null;
        $data['eligible_count'] = $eligible === null ? null : (int) $eligible;

        $groups = array_values(array_filter(
            array_map(fn ($name) => trim((string) $name), $validated['exposure_groups'] ?? []),
            fn (string $name) => $name !== '',
        ));
        $data['exposure_groups'] = $groups === [] ? null : $groups;

        return $data;
    }
}
