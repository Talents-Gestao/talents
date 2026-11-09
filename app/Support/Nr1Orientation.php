<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Orientação NR-1 (Caderno §6): conteúdo sempre incluído na campanha e visível a todo colaborador, sem contratação.
 * Texto fixo; vídeo de abertura vem da biblioteca global quando a campanha o exige.
 */
final class Nr1Orientation
{
    /**
     * @return array{title: string, availability: string, paragraphs: list<string>, video_url: ?string, video_required: bool, video_title: ?string}
     */
    public static function content(?string $videoUrl = null, bool $videoRequired = false, ?string $videoTitle = null): array
    {
        return [
            'title' => 'Orientação sobre a pesquisa',
            'availability' => 'Disponível para todos',
            'paragraphs' => [
                'Esta pesquisa faz parte do cuidado da empresa com a saúde e o bem-estar de quem trabalha nela, conforme a NR-1.',
                'Suas respostas são anônimas: ninguém na empresa vê o que você respondeu, e os resultados só aparecem reunidos em grupos.',
                'Não existem respostas certas ou erradas. Responda pensando no seu dia a dia de trabalho, com sinceridade, e escolha sempre a opção que mais se aproxima da frequência com que a situação acontece.',
                'Leva poucos minutos. Você pode voltar à pergunta anterior antes de enviar.',
            ],
            'video_url' => $videoUrl,
            'video_required' => $videoRequired,
            'video_title' => $videoTitle,
        ];
    }
}
