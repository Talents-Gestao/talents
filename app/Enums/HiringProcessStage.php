<?php

declare(strict_types=1);

namespace App\Enums;

enum HiringProcessStage: string
{
    case EngenhariaCargo = 'engenharia_cargo';
    case AnuncioVagas = 'anuncio_vagas';
    case AnaliseCurriculo = 'analise_curriculo';
    case AnaliseComportamental = 'analise_comportamental';
    case EntrevistaPresencial = 'entrevista_presencial';
    case EntrevistaGestor = 'entrevista_gestor';
    case VisitaEmpresa = 'visita_empresa';
    case Contratacao = 'contratacao';

    public function label(): string
    {
        return match ($this) {
            self::EngenhariaCargo => 'Engenharia de Cargo',
            self::AnuncioVagas => 'Anúncio de Vagas',
            self::AnaliseCurriculo => 'Análise de currículo',
            self::AnaliseComportamental => 'Análise Comportamental',
            self::EntrevistaPresencial => 'Entrevista Presencial',
            self::EntrevistaGestor => 'Entrevista com o Gestor',
            self::VisitaEmpresa => 'Visita à Empresa (Opcional)',
            self::Contratacao => 'Contratação',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::EngenhariaCargo => 1,
            self::AnuncioVagas => 2,
            self::AnaliseCurriculo => 3,
            self::AnaliseComportamental => 4,
            self::EntrevistaPresencial => 5,
            self::EntrevistaGestor => 6,
            self::VisitaEmpresa => 7,
            self::Contratacao => 8,
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::EngenhariaCargo,
            self::AnuncioVagas,
            self::AnaliseCurriculo,
            self::AnaliseComportamental,
            self::EntrevistaPresencial,
            self::EntrevistaGestor,
            self::VisitaEmpresa,
            self::Contratacao,
        ];
    }

    public function next(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        if ($index === false || $index >= count($ordered) - 1) {
            return null;
        }

        return $ordered[$index + 1];
    }

    public function previous(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        if ($index === false || $index <= 0) {
            return null;
        }

        return $ordered[$index - 1];
    }

    /**
     * @return list<array{value: string, label: string, order: int}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $stage) => [
                'value' => $stage->value,
                'label' => $stage->label(),
                'order' => $stage->order(),
            ],
            self::ordered(),
        );
    }
}
