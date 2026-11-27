<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SurveyGroupResult;
use App\Models\SurveyResult;

/**
 * Título da dimensão de um resultado: uma regra só para o painel, o plano de ação e o prompt da Mia. Vale o título atual
 * da seção do instrumento; o título gravado no cálculo (meta.section_title) só entra quando a seção não existe mais.
 */
final class Nr1DimensionTitle
{
    public static function of(SurveyResult|SurveyGroupResult $result): string
    {
        $title = $result->section?->title ?? data_get($result->meta, 'section_title');

        return filled($title) ? (string) $title : 'Dimensão';
    }
}
