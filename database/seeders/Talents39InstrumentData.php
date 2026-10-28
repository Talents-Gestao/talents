<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Instrumento Talents NR-1 V11: 39 afirmações, 7 dimensões, escala de frequência (Nunca…Sempre).
 * Transcrição do Apêndice A do Caderno NR-1 V11. Polaridade Direta = enunciado desfavorável (escore = resposta);
 * Invertida = enunciado favorável (escore = 6 − resposta). Texto, dimensão e polaridade dependem de homologação
 * da responsável técnica: qualquer mudança exige NOVA versão do instrumento.
 */
final class Talents39InstrumentData
{
    public const TEMPLATE_TITLE = 'Talents NR-1 V11 (39 itens)';

    public const VERSION_LABEL = 'v11-1';

    public const REFERENCE = 'Caderno NR-1 V11, Apêndice A (22/10/2026).';

    /**
     * @return list<array{id: int, dimension: string, body: string, reverse_score: bool}>
     */
    public static function items(): array
    {
        return [
            ['id' => 1, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Minha rotina de trabalho exige um ritmo excessivo de produtividade.', 'reverse_score' => false],
            ['id' => 2, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Frequentemente preciso deixar tarefas incompletas por excesso de demandas.', 'reverse_score' => false],
            ['id' => 3, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Sinto dificuldade em equilibrar qualidade e volume de trabalho.', 'reverse_score' => false],
            ['id' => 4, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Meu trabalho exige atenção constante por longos períodos.', 'reverse_score' => false],
            ['id' => 5, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Recebo demandas urgentes com frequência excessiva.', 'reverse_score' => false],
            ['id' => 6, 'dimension' => 'Demandas e Sobrecarga', 'body' => 'Sinto desgaste mental ao final do expediente.', 'reverse_score' => false],
            ['id' => 7, 'dimension' => 'Autonomia e Organização', 'body' => 'Tenho clareza sobre minhas responsabilidades.', 'reverse_score' => true],
            ['id' => 8, 'dimension' => 'Autonomia e Organização', 'body' => 'Consigo organizar minhas atividades com autonomia.', 'reverse_score' => true],
            ['id' => 9, 'dimension' => 'Autonomia e Organização', 'body' => 'Tenho acesso às informações necessárias para executar meu trabalho.', 'reverse_score' => true],
            ['id' => 10, 'dimension' => 'Autonomia e Organização', 'body' => 'As prioridades do meu trabalho são comunicadas com clareza.', 'reverse_score' => true],
            ['id' => 11, 'dimension' => 'Autonomia e Organização', 'body' => 'Consigo participar de decisões relacionadas à minha rotina profissional.', 'reverse_score' => true],
            ['id' => 12, 'dimension' => 'Liderança e Gestão', 'body' => 'Minha liderança demonstra respeito no dia a dia.', 'reverse_score' => true],
            ['id' => 13, 'dimension' => 'Liderança e Gestão', 'body' => 'Recebo apoio da liderança quando enfrento dificuldades.', 'reverse_score' => true],
            ['id' => 14, 'dimension' => 'Liderança e Gestão', 'body' => 'Consigo conversar com minha liderança sem receio.', 'reverse_score' => true],
            ['id' => 15, 'dimension' => 'Liderança e Gestão', 'body' => 'Recebo feedbacks claros sobre meu desempenho.', 'reverse_score' => true],
            ['id' => 16, 'dimension' => 'Liderança e Gestão', 'body' => 'Minha liderança age de forma coerente com o que comunica.', 'reverse_score' => true],
            ['id' => 17, 'dimension' => 'Liderança e Gestão', 'body' => 'Sinto que meu trabalho é reconhecido pela liderança.', 'reverse_score' => true],
            ['id' => 18, 'dimension' => 'Segurança Psicológica', 'body' => 'Sinto segurança para expressar opiniões no ambiente de trabalho.', 'reverse_score' => true],
            ['id' => 19, 'dimension' => 'Segurança Psicológica', 'body' => 'Posso relatar problemas sem medo de represálias.', 'reverse_score' => true],
            ['id' => 20, 'dimension' => 'Segurança Psicológica', 'body' => 'Erros são tratados de forma construtiva na empresa.', 'reverse_score' => true],
            ['id' => 21, 'dimension' => 'Segurança Psicológica', 'body' => 'Sinto que minhas ideias são respeitadas.', 'reverse_score' => true],
            ['id' => 22, 'dimension' => 'Segurança Psicológica', 'body' => 'Existe abertura para diálogo entre equipes.', 'reverse_score' => true],
            ['id' => 23, 'dimension' => 'Segurança Psicológica', 'body' => 'O ambiente de trabalho favorece respeito e acolhimento.', 'reverse_score' => true],
            ['id' => 24, 'dimension' => 'Relacionamentos e Clima', 'body' => 'Existe colaboração entre as equipes.', 'reverse_score' => true],
            ['id' => 25, 'dimension' => 'Relacionamentos e Clima', 'body' => 'Os conflitos são tratados de forma adequada.', 'reverse_score' => true],
            ['id' => 26, 'dimension' => 'Relacionamentos e Clima', 'body' => 'Sinto respeito nas relações profissionais.', 'reverse_score' => true],
            ['id' => 27, 'dimension' => 'Relacionamentos e Clima', 'body' => 'O ambiente de trabalho é emocionalmente saudável.', 'reverse_score' => true],
            ['id' => 28, 'dimension' => 'Relacionamentos e Clima', 'body' => 'Sinto que faço parte da empresa.', 'reverse_score' => true],
            ['id' => 29, 'dimension' => 'Relacionamentos e Clima', 'body' => 'Existe equilíbrio na comunicação entre os setores.', 'reverse_score' => true],
            ['id' => 30, 'dimension' => 'Esgotamento e Saúde Emocional', 'body' => 'Tenho sentido cansaço emocional relacionado ao trabalho.', 'reverse_score' => false],
            ['id' => 31, 'dimension' => 'Esgotamento e Saúde Emocional', 'body' => 'Sinto dificuldade para desconectar do trabalho fora do expediente.', 'reverse_score' => false],
            ['id' => 32, 'dimension' => 'Esgotamento e Saúde Emocional', 'body' => 'Tenho percebido aumento do estresse na minha rotina profissional.', 'reverse_score' => false],
            ['id' => 33, 'dimension' => 'Esgotamento e Saúde Emocional', 'body' => 'Meu trabalho tem impactado negativamente meu bem-estar emocional.', 'reverse_score' => false],
            ['id' => 34, 'dimension' => 'Esgotamento e Saúde Emocional', 'body' => 'Tenho sentido desmotivação frequente no trabalho.', 'reverse_score' => false],
            ['id' => 35, 'dimension' => 'Percepção Organizacional', 'body' => 'A empresa demonstra preocupação genuína com os colaboradores.', 'reverse_score' => true],
            ['id' => 36, 'dimension' => 'Percepção Organizacional', 'body' => 'As decisões internas são comunicadas com transparência.', 'reverse_score' => true],
            ['id' => 37, 'dimension' => 'Percepção Organizacional', 'body' => 'Sinto confiança na empresa onde trabalho.', 'reverse_score' => true],
            ['id' => 38, 'dimension' => 'Percepção Organizacional', 'body' => 'A cultura da empresa é coerente com o que é praticado.', 'reverse_score' => true],
            ['id' => 39, 'dimension' => 'Percepção Organizacional', 'body' => 'Vejo preocupação da empresa com saúde emocional e bem-estar.', 'reverse_score' => true],
        ];
    }

    /**
     * Dimensões na ordem do instrumento.
     *
     * @return list<string>
     */
    public static function dimensions(): array
    {
        return [
            'Demandas e Sobrecarga',
            'Autonomia e Organização',
            'Liderança e Gestão',
            'Segurança Psicológica',
            'Relacionamentos e Clima',
            'Esgotamento e Saúde Emocional',
            'Percepção Organizacional',
        ];
    }
}
