<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Os três cartões de serviço da tela «Soluções Talents» (HTML aprovado). Texto fixo; a recomendação por dimensão vem das
 * regras configuráveis (Nr1ServiceMatch).
 */
final class Nr1ServiceCards
{
    /**
     * @return list<array{number: string, title: string, status: string, tone: string, text: string}>
     */
    public static function all(): array
    {
        return [
            ['number' => '01', 'title' => 'Pesquisa Psicossocial', 'status' => 'Serviço realizado', 'tone' => 'ok', 'text' => 'Aplicação da pesquisa, análise dos resultados e apresentação do relatório e plano de ação.'],
            ['number' => '02', 'title' => 'Avaliação Complementar', 'status' => 'Sob proposta', 'tone' => 'info', 'text' => 'Entrevistas, análise documental, observação de atividades e aprofundamento técnico quando indicado.'],
            ['number' => '03', 'title' => 'Intervenção Organizacional', 'status' => 'Sob proposta', 'tone' => 'warn', 'text' => 'Projetos, treinamentos e ações para apoiar a implementação das melhorias.'],
        ];
    }
}
