<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Services\Feedback\Ai\FeedbackAiException;
use Illuminate\Support\Facades\Validator;

/**
 * Esquema do relatório estruturado do feedback (JSON devolvido pela IA), validado no servidor antes de gravar.
 * Também verifica os trechos (precisam existir na transcrição; senão ficam nulos) e tira do relatório qualquer
 * detalhe de assunto sensível (saúde, família, finanças), deixando só a sinalização «assunto sensível citado».
 */
final class FeedbackReportSchema
{
    public const VERSION = 'feedback_report.v1';

    public const INVALID_MESSAGE = 'A IA devolveu o relatório num formato inválido. Tente gerar de novo.';

    /** Taxonomia fixa de temas (chave estável → rótulo). */
    public const THEMES = [
        'comunicacao' => 'Comunicação',
        'entregas_resultados' => 'Entregas e resultados',
        'qualidade' => 'Qualidade do trabalho',
        'prazos_organizacao' => 'Prazos e organização',
        'colaboracao_equipe' => 'Colaboração e equipe',
        'autonomia_iniciativa' => 'Autonomia e iniciativa',
        'desenvolvimento_aprendizado' => 'Desenvolvimento e aprendizado',
        'carreira_crescimento' => 'Carreira e crescimento',
        'reconhecimento' => 'Reconhecimento',
        'engajamento_motivacao' => 'Engajamento e motivação',
        'processos_ferramentas' => 'Processos e ferramentas',
        'relacionamento_clientes' => 'Relacionamento com clientes',
        'comportamento_postura' => 'Comportamento e postura',
        'lideranca_gestao' => 'Liderança e gestão',
        'carga_trabalho' => 'Carga de trabalho',
        'outros' => 'Outros',
    ];

    public const SENTIMENTS = [
        'positivo' => 'Positivo',
        'neutro' => 'Neutro',
        'atencao' => 'Atenção',
        'misto' => 'Misto',
    ];

    public const OWNERS = [
        'lider' => 'Líder',
        'colaborador' => 'Colaborador',
        'ambos' => 'Líder e colaborador',
        'nao_citado' => 'Responsável não citado',
    ];

    public const SPEAKERS = [
        'lider' => 'Líder',
        'colaborador' => 'Colaborador',
        'outro' => 'Outra pessoa',
    ];

    /** Saúde, família e finanças pessoais: só sinalizar, nunca detalhar. */
    public const SENSITIVE_PATTERN = '/\b(sa[uú]de|doen[cç]a|doente|m[eé]dic[oa]s?|consulta m[eé]dica|diagn[oó]stic\w*|rem[eé]dio|medica[cç][aã]o|terapia|terapeuta|psic[oó]log\w*|psiquiatr\w*|ansiedade|depress[aã]o|gravidez|gr[aá]vida|luto|falec\w*|div[oó]rcio|separa[cç][aã]o|d[ií]vidas?|endivid\w*|empr[eé]stimo|cirurgia|hospital\w*|internad[oa]|exames?|diabetes|fisioterapia|febre|ins[oô]nia|atestado|licen[cç]a m[eé]dica|fam[ií]lia|filh[oa]s?|m[aã]e|pai|irm[aã]o|irm[aã]|adiantamento)\b/iu';

    /** Descrição do esquema para o prompt (o mesmo que a validação aplica). */
    public static function promptDescription(): string
    {
        $themes = implode(', ', array_keys(self::THEMES));
        $sentiments = implode(', ', array_keys(self::SENTIMENTS));
        $owners = implode(', ', array_keys(self::OWNERS));
        $speakers = implode(', ', array_keys(self::SPEAKERS));

        return <<<TXT
{
  "resumo": "string (até 1200 caracteres)",
  "pontos_fortes": [{"texto": "string (até 300)", "trecho": "string copiada da transcrição (até 240) ou null"}],
  "pontos_atencao": [{"texto": "string (até 300)", "trecho": "string copiada da transcrição (até 240)"}],
  "temas": [{"chave": "uma de: {$themes}", "sentimento": "um de: {$sentiments}"}],
  "compromissos": [{"id": "c1, c2…", "descricao": "string (até 300)", "responsavel": "um de: {$owners}", "prazo": "como foi dito (até 60) ou null", "trecho": "string copiada da transcrição (até 240)"}],
  "trechos_chave": [{"texto": "string copiada da transcrição (até 240)", "falante": "um de: {$speakers}"}],
  "nao_afirmado": ["string (até 300): o que não pôde ser afirmado com segurança"],
  "assunto_sensivel_citado": true ou false
}
Limites: até 8 itens em pontos_fortes, pontos_atencao, temas e nao_afirmado; até 10 compromissos; até 6 trechos_chave.
TXT;
    }

    /**
     * Interpreta o texto devolvido pela IA, valida e normaliza.
     *
     * @return array<string, mixed>
     *
     * @throws FeedbackAiException
     */
    public static function parse(string $raw, string $transcript): array
    {
        $data = self::decode($raw);

        $validator = Validator::make($data, [
            'resumo' => ['required', 'string', 'min:1', 'max:1200'],
            'pontos_fortes' => ['present', 'array', 'max:8'],
            'pontos_fortes.*.texto' => ['required', 'string', 'max:300'],
            'pontos_fortes.*.trecho' => ['nullable', 'string', 'max:240'],
            'pontos_atencao' => ['present', 'array', 'max:8'],
            'pontos_atencao.*.texto' => ['required', 'string', 'max:300'],
            'pontos_atencao.*.trecho' => ['required', 'string', 'max:240'],
            'temas' => ['present', 'array', 'max:8'],
            'temas.*.chave' => ['required', 'string', 'in:'.implode(',', array_keys(self::THEMES))],
            'temas.*.sentimento' => ['required', 'string', 'in:'.implode(',', array_keys(self::SENTIMENTS))],
            'compromissos' => ['present', 'array', 'max:10'],
            'compromissos.*.id' => ['required', 'string', 'regex:/^c\d{1,3}$/'],
            'compromissos.*.descricao' => ['required', 'string', 'max:300'],
            'compromissos.*.responsavel' => ['required', 'string', 'in:'.implode(',', array_keys(self::OWNERS))],
            'compromissos.*.prazo' => ['nullable', 'string', 'max:60'],
            'compromissos.*.trecho' => ['required', 'string', 'max:240'],
            'trechos_chave' => ['present', 'array', 'max:6'],
            'trechos_chave.*.texto' => ['required', 'string', 'max:240'],
            'trechos_chave.*.falante' => ['required', 'string', 'in:'.implode(',', array_keys(self::SPEAKERS))],
            'nao_afirmado' => ['present', 'array', 'max:8'],
            'nao_afirmado.*' => ['required', 'string', 'max:300'],
            'assunto_sensivel_citado' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_report: '.mb_substr((string) $validator->errors()->keys()[0], 0, 40));
        }

        $ids = array_map(fn ($c) => $c['id'], $data['compromissos']);
        if (count($ids) !== count(array_unique($ids))) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_report: compromissos.id');
        }

        return self::sanitize(self::normalize($data), $transcript);
    }

    /** @return array<string, mixed> */
    private static function decode(string $raw): array
    {
        $raw = trim($raw);
        $raw = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_report: json');
        }

        $data = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (! is_array($data) || array_is_list($data)) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_report: json');
        }

        return $data;
    }

    /**
     * Só as chaves do esquema, na ordem do esquema.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $str = fn ($v) => $v === null ? null : trim((string) $v);

        return [
            'resumo' => trim((string) $data['resumo']),
            'pontos_fortes' => array_values(array_map(fn ($p) => ['texto' => $str($p['texto']), 'trecho' => $str($p['trecho'] ?? null) ?: null], $data['pontos_fortes'])),
            'pontos_atencao' => array_values(array_map(fn ($p) => ['texto' => $str($p['texto']), 'trecho' => $str($p['trecho']) ?: null], $data['pontos_atencao'])),
            'temas' => array_values(array_map(fn ($t) => ['chave' => $t['chave'], 'sentimento' => $t['sentimento']], $data['temas'])),
            'compromissos' => array_values(array_map(fn ($c) => [
                'id' => $c['id'],
                'descricao' => $str($c['descricao']),
                'responsavel' => $c['responsavel'],
                'prazo' => $str($c['prazo'] ?? null) ?: null,
                'trecho' => $str($c['trecho']) ?: null,
            ], $data['compromissos'])),
            'trechos_chave' => array_values(array_map(fn ($t) => ['texto' => $str($t['texto']), 'falante' => $t['falante']], $data['trechos_chave'])),
            'nao_afirmado' => array_values(array_map(fn ($n) => trim((string) $n), $data['nao_afirmado'])),
            'assunto_sensivel_citado' => (bool) $data['assunto_sensivel_citado'],
        ];
    }

    /**
     * Trecho que não está na transcrição vira nulo (a IA não pode citar o que não foi dito). Item que detalha
     * assunto sensível sai do relatório e liga a sinalização.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private static function sanitize(array $report, string $transcript): array
    {
        $haystack = self::comparable($transcript);
        $verify = fn (?string $quote) => ($quote !== null && $quote !== '' && str_contains($haystack, self::comparable($quote))) ? $quote : null;
        $sensitive = $report['assunto_sensivel_citado'];
        $isSensitive = function (?string ...$texts) use (&$sensitive): bool {
            foreach ($texts as $text) {
                if ($text !== null && preg_match(self::SENSITIVE_PATTERN, $text)) {
                    $sensitive = true;

                    return true;
                }
            }

            return false;
        };

        $sentences = preg_split('/(?<=[.!?])\s+/u', $report['resumo']) ?: [$report['resumo']];
        $resumo = trim(implode(' ', array_filter($sentences, fn ($s) => ! $isSensitive($s))));

        $filter = fn (array $items, callable $keep) => array_values(array_filter(array_map($keep, $items)));

        $report['pontos_fortes'] = $filter($report['pontos_fortes'], fn ($p) => $isSensitive($p['texto'], $p['trecho']) ? null : ['texto' => $p['texto'], 'trecho' => $verify($p['trecho'])]);
        $report['pontos_atencao'] = $filter($report['pontos_atencao'], fn ($p) => $isSensitive($p['texto'], $p['trecho']) ? null : ['texto' => $p['texto'], 'trecho' => $verify($p['trecho'])]);
        $report['compromissos'] = $filter($report['compromissos'], fn ($c) => $isSensitive($c['descricao'], $c['trecho'], $c['prazo']) ? null : array_merge($c, ['trecho' => $verify($c['trecho'])]));
        $report['trechos_chave'] = $filter($report['trechos_chave'], fn ($t) => ($isSensitive($t['texto']) || $verify($t['texto']) === null) ? null : $t);
        $report['nao_afirmado'] = $filter($report['nao_afirmado'], fn ($n) => $isSensitive($n) ? null : $n);

        $report['resumo'] = $resumo !== '' ? $resumo : 'Resumo omitido: a conversa tratou de assunto sensível.';
        $report['assunto_sensivel_citado'] = $sensitive;

        return $report;
    }

    private static function comparable(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim($text);
    }
}
