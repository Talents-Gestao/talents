<?php

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\CompanyRhidScheduleSetting;

class PunchScheduleSettingsService
{
    /** @var list<string> */
    public const DAY_KEYS = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];

    /**
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        $dias = [];
        foreach (self::DAY_KEYS as $k) {
            $dias[$k] = $this->defaultDay();
        }

        return [
            'segundo_trabalho' => false,
            'segundo_almoco' => false,
            'tolerancia_minutos' => 15,
            'dias' => $dias,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultDay(): array
    {
        return [
            'ativo' => false,
            'entrada' => null,
            'saida_almoco' => null,
            'volta_almoco' => null,
            'saida' => null,
            'almoco2_inicio' => null,
            'almoco2_fim' => null,
            'trabalho2_entrada' => null,
            'trabalho2_saida' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public function normalize(?array $stored): array
    {
        $base = $this->defaultSettings();
        if (! is_array($stored)) {
            return $base;
        }
        $base['segundo_trabalho'] = (bool) ($stored['segundo_trabalho'] ?? false);
        $base['segundo_almoco'] = (bool) ($stored['segundo_almoco'] ?? false);
        $tm = $stored['tolerancia_minutos'] ?? 15;
        $base['tolerancia_minutos'] = is_numeric($tm) ? max(0, min(120, (int) $tm)) : 15;
        $inDays = $stored['dias'] ?? [];
        foreach (self::DAY_KEYS as $k) {
            $base['dias'][$k] = $this->mergeDay(is_array($inDays[$k] ?? null) ? $inDays[$k] : []);
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    public function mergeDay(array $day): array
    {
        $d = $this->defaultDay();
        $d['ativo'] = (bool) ($day['ativo'] ?? false);
        foreach ([
            'entrada', 'saida_almoco', 'volta_almoco', 'saida',
            'almoco2_inicio', 'almoco2_fim', 'trabalho2_entrada', 'trabalho2_saida',
        ] as $field) {
            $v = $day[$field] ?? null;
            $d[$field] = is_string($v) && preg_match('/^\d{2}:\d{2}$/', $v) ? $v : null;
        }

        return $d;
    }

    public function getForCompany(Company $company): array
    {
        $row = CompanyRhidScheduleSetting::query()->where('company_id', $company->id)->first();

        return $this->normalize($row?->settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function saveForCompany(Company $company, array $settings): CompanyRhidScheduleSetting
    {
        $normalized = $this->normalize($settings);

        return CompanyRhidScheduleSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            ['settings' => $normalized],
        );
    }
}
