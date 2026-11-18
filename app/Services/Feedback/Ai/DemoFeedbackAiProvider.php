<?php

declare(strict_types=1);

namespace App\Services\Feedback\Ai;

use App\Models\AiUsageLog;
use App\Support\Feedback\Ai\FeedbackAiGate;
use App\Support\Feedback\Ai\FeedbackReportSchema;

/**
 * PROVEDOR DE DEMONSTRAÇÃO (só APP_ENV=local): devolve saídas determinísticas, no mesmo formato da IA real,
 * sem chamar nenhuma API externa. A limpeza tira vícios de fala e repetições por regras simples; o relatório é
 * montado por palavras-chave, com trechos copiados da própria transcrição. Serve para percorrer o fluxo na tela,
 * nunca para uso real. Fora do ambiente local, recusa.
 */
class DemoFeedbackAiProvider
{
    private const FILLERS = [
        '/\b(?:é{2,}|hã+|hum+|ãh+|ahn?|uhm+)\b[,.…]*\s*/iu',
        '/(?<!\p{L})[Éé](?:\.\.\.|…)\s*/u',
        '/,?\s*\bné\b\s*[?,]?/iu',
        '/\btipo\s*,\s*/iu',
        '/\bentão\s*,\s*(?=então\b)/iu',
        '/\b(?:assim|sabe)\s*,\s*(?=\p{L})/iu',
    ];

    private const THEME_KEYWORDS = [
        'prazos_organizacao' => '/\b(prazo|atras\w*|cronograma|organiza\w*|planilha)\b/iu',
        'relacionamento_clientes' => '/\b(cliente\w*|atendimento)\b/iu',
        'entregas_resultados' => '/\b(entrega\w*|resultado\w*|meta\w*|n[uú]meros?)\b/iu',
        'desenvolvimento_aprendizado' => '/\b(curso\w*|aprend\w*|treinamento|capacita\w*|estud\w*)\b/iu',
        'colaboracao_equipe' => '/\b(equipe|time|colega\w*|ajud\w*)\b/iu',
        'reconhecimento' => '/\b(parab[eé]ns|reconhe\w*|mandou bem|excelente)\b/iu',
        'carreira_crescimento' => '/\b(carreira|promo[cç][aã]o|crescimento|cargo)\b/iu',
        'processos_ferramentas' => '/\b(processo\w*|ferramenta\w*|sistema|fluxo)\b/iu',
        'comunicacao' => '/\b(comunica\w*|alinha\w*|retorno|avisar)\b/iu',
        'carga_trabalho' => '/\b(sobrecarg\w*|demanda\w*|volume)\b/iu',
    ];

    private const STRENGTH = '/\b(parab[eé]ns|muito bom|[oó]timo trabalho|excelente|mandou bem|destaque|gostei muito|evolu[ií]\w*)\b/iu';

    private const ATTENTION = '/\b(atras\w*|pendente\w*|pend[eê]ncia\w*|melhorar|dificuldade\w*|preocupa\w*|incomplet\w*|aten[cç][aã]o|esqueceu|demorou)\b/iu';

    private const COMMITMENT = '/\b(vou |eu vou|combinado|me comprometo|fica combinado|at[eé] (?:o )?(?:dia \d+|sexta|segunda|ter[cç]a|quarta|quinta|o fim do m[eê]s|a pr[oó]xima semana|\d{1,2}\/\d{1,2}))/iu';

    private const DEADLINE = '/\bat[eé] (?:o |a )?(dia \d{1,2}(?: de \p{L}+)?|sexta(?:-feira)?|segunda(?:-feira)?|ter[cç]a(?:-feira)?|quarta(?:-feira)?|quinta(?:-feira)?|fim do m[eê]s|pr[oó]xima semana|\d{1,2}\/\d{1,2})/iu';

    /**
     * @return array{text: string, input_tokens: int, output_tokens: int}
     *
     * @throws FeedbackAiException
     */
    public function complete(string $purpose, string $userContent): array
    {
        if (! FeedbackAiGate::demoAvailable()) {
            throw new FeedbackAiException(FeedbackAiGate::LOCKED_MESSAGE, 'demo_outside_local');
        }

        if ($purpose === AiUsageLog::PURPOSE_FEEDBACK_EVOLUTION) {
            $text = (string) json_encode($this->evolution($this->between($userContent, 'relatorios')), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            return [
                'text' => $text,
                'input_tokens' => (int) ceil(mb_strlen($userContent) / 4),
                'output_tokens' => (int) ceil(mb_strlen($text) / 4),
            ];
        }

        $transcript = $this->between($userContent, 'transcript');

        $text = $purpose === AiUsageLog::PURPOSE_FEEDBACK_REPORT
            ? (string) json_encode($this->report($transcript), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : $this->clean($transcript);

        return [
            'text' => $text,
            'input_tokens' => (int) ceil(mb_strlen($userContent) / 4),
            'output_tokens' => (int) ceil(mb_strlen($text) / 4),
        ];
    }

    private function between(string $content, string $tag): string
    {
        $start = mb_strpos($content, "<{$tag}>");
        $end = mb_strrpos($content, "</{$tag}>");
        if ($start === false || $end === false) {
            return '';
        }
        $start += mb_strlen("<{$tag}>");

        return trim(mb_substr($content, $start, $end - $start));
    }

    private function clean(string $transcript): string
    {
        $time = '\[?(\d{1,2}:\d{2}(?::\d{2})?)\]?';
        $paragraphs = [];
        $pending = null;

        foreach (preg_split('/\R/u', $transcript) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Cabeçalho «00:00:01 Líder» ou «Líder 00:00:01»: a fala vem na linha seguinte.
            if (preg_match('/^'.$time.'\s+([\p{L} ]{2,40}?\d*)$/u', $line, $m)) {
                $pending = ['time' => $this->hms($m[1]), 'who' => trim($m[2])];

                continue;
            }
            if (preg_match('/^([\p{L} ]{2,40}?\d*)\s+'.$time.'$/u', $line, $m)) {
                $pending = ['time' => $this->hms($m[2]), 'who' => trim($m[1])];

                continue;
            }

            // Linha logo depois de um cabeçalho é sempre fala, mesmo que comece com «Outro ponto: …».
            if ($pending !== null) {
                $paragraphs[] = "[{$pending['time']}] {$pending['who']}: ".$this->tidy($line);
                $pending = null;

                continue;
            }

            if (preg_match('/^(?:'.$time.'\s*)?([\p{L} ]{2,40}?\d*)\s*:\s+(.+)$/u', $line, $m)) {
                $stamp = $m[1] !== '' ? $this->hms($m[1]) : null;
                $paragraphs[] = ($stamp ? "[{$stamp}] " : '').trim($m[2]).': '.$this->tidy($m[3]);

                continue;
            }

            if ($paragraphs !== []) {
                $paragraphs[count($paragraphs) - 1] .= ' '.$this->tidy($line);
            } else {
                $paragraphs[] = $this->tidy($line);
            }
        }

        return implode("\n\n", $paragraphs);
    }

    private function tidy(string $speech): string
    {
        foreach (self::FILLERS as $pattern) {
            $speech = (string) preg_replace($pattern, '', $speech);
        }
        $speech = (string) preg_replace('/\b(\p{L}+)(?:\s+\1\b)+/iu', '$1', $speech);
        $speech = (string) preg_replace('/\s+([,.?!])/u', '$1', $speech);
        $speech = (string) preg_replace('/\s{2,}/u', ' ', trim($speech));
        $speech = ltrim($speech, ', ');

        return $speech === '' ? '[trecho incerto]' : mb_strtoupper(mb_substr($speech, 0, 1)).mb_substr($speech, 1);
    }

    private function hms(string $time): string
    {
        $parts = array_map('intval', explode(':', $time));
        if (count($parts) === 2) {
            array_unshift($parts, 0);
        }

        return sprintf('%02d:%02d:%02d', ...$parts);
    }

    /** @return array<string, mixed> */
    private function report(string $transcript): array
    {
        $turns = [];
        foreach (preg_split('/\R+/u', $transcript) ?: [] as $line) {
            if (preg_match('/^(?:\[[\d:]+\]\s*)?([\p{L} ]{2,40}?\d*)\s*:\s+(.+)$/u', trim($line), $m)) {
                $who = mb_strtolower(trim($m[1]));
                $turns[] = [
                    'speaker' => str_starts_with($who, 'líder') ? 'lider' : (str_starts_with($who, 'colaborador') ? 'colaborador' : 'outro'),
                    'text' => trim($m[2]),
                ];
            }
        }

        $find = function (string $pattern, int $limit, ?string $speaker = null) use ($turns): array {
            $hits = [];
            foreach ($turns as $turn) {
                if ($speaker !== null && $turn['speaker'] !== $speaker) {
                    continue;
                }
                $sentence = $this->sentence($turn['text'], $pattern);
                // Frases muito curtas («Combinado.») não dizem o quê: ficam de fora.
                if ($sentence !== null && count(preg_split('/\s+/u', $sentence) ?: []) >= 5 && ! preg_match(FeedbackReportSchema::SENSITIVE_PATTERN, $sentence)) {
                    $hits[] = ['speaker' => $turn['speaker'], 'sentence' => $sentence];
                }
                if (count($hits) >= $limit) {
                    break;
                }
            }

            return $hits;
        };

        $themes = [];
        foreach (self::THEME_KEYWORDS as $key => $pattern) {
            $count = count(array_filter($turns, fn ($t) => preg_match($pattern, $t['text'])));
            if ($count > 0) {
                $themes[$key] = $count;
            }
        }
        arsort($themes);
        $themes = array_slice($themes, 0, 5, true);

        $strengths = $find(self::STRENGTH, 3, 'lider');
        $attention = $find(self::ATTENTION, 3, 'lider');
        $commitments = $find(self::COMMITMENT, 5);

        $sentiment = function (string $key) use ($turns): string {
            $pattern = self::THEME_KEYWORDS[$key];
            $related = array_filter($turns, fn ($t) => preg_match($pattern, $t['text']));
            $good = count(array_filter($related, fn ($t) => preg_match(self::STRENGTH, $t['text'])));
            $bad = count(array_filter($related, fn ($t) => preg_match(self::ATTENTION, $t['text'])));

            return match (true) {
                $good > 0 && $bad > 0 => 'misto',
                $good > 0 => 'positivo',
                $bad > 0 => 'atencao',
                default => 'neutro',
            };
        };

        $vague = array_filter($turns, fn ($t) => preg_match('/\b(semana que vem|mais pra frente|depois a gente v[eê]|qualquer hora)\b/iu', $t['text']));
        $notAffirmed = [];
        if ($vague !== []) {
            $notAffirmed[] = 'Há prazo citado de forma vaga (por exemplo, «semana que vem»), sem data definida.';
        }
        $notAffirmed[] = 'Não é possível afirmar como o colaborador avalia o próprio desempenho além do que foi dito na conversa.';

        $themeLabels = array_map(fn ($k) => mb_strtolower(FeedbackReportSchema::THEMES[$k]), array_keys($themes));
        $leaderTurns = count(array_filter($turns, fn ($t) => $t['speaker'] === 'lider'));
        $employeeTurns = count(array_filter($turns, fn ($t) => $t['speaker'] === 'colaborador'));

        return [
            'resumo' => sprintf(
                'Conversa de feedback com %d falas do líder e %d do colaborador. Os assuntos mais presentes foram %s. %s',
                $leaderTurns,
                $employeeTurns,
                $themeLabels === [] ? 'gerais' : implode(', ', $themeLabels),
                $commitments === [] ? 'Não foram identificados compromissos explícitos.' : 'Foram combinados '.count($commitments).' compromissos ao longo da conversa.',
            ),
            'pontos_fortes' => array_map(fn ($h) => ['texto' => 'Reconhecimento feito pelo líder durante a conversa.', 'trecho' => $h['sentence']], $strengths),
            'pontos_atencao' => array_map(fn ($h) => ['texto' => 'Ponto que pede acompanhamento, citado na conversa.', 'trecho' => $h['sentence']], $attention),
            'temas' => array_map(fn ($k) => ['chave' => $k, 'sentimento' => $sentiment($k)], array_keys($themes)),
            'compromissos' => array_values(array_map(function ($h, $i) {
                preg_match(self::DEADLINE, $h['sentence'], $d);

                return [
                    'id' => 'c'.($i + 1),
                    'descricao' => mb_substr($h['sentence'], 0, 300),
                    'responsavel' => in_array($h['speaker'], ['lider', 'colaborador'], true) ? $h['speaker'] : 'nao_citado',
                    'prazo' => isset($d[0]) ? mb_substr($d[0], 0, 60) : null,
                    'trecho' => $h['sentence'],
                ];
            }, $commitments, array_keys($commitments))),
            'trechos_chave' => array_slice(array_map(
                fn ($h) => ['texto' => $h['sentence'], 'falante' => $h['speaker']],
                array_merge($strengths, $commitments),
            ), 0, 4),
            'nao_afirmado' => $notAffirmed,
            'assunto_sensivel_citado' => (bool) preg_match(FeedbackReportSchema::SENSITIVE_PATTERN, $transcript),
        ];
    }

    /**
     * Evolução de demonstração, só a partir dos relatórios estruturados recebidos (sem julgamento sobre a pessoa).
     *
     * @return array<string, mixed>
     */
    private function evolution(string $json): array
    {
        $periods = json_decode($json, true);
        $periods = is_array($periods) ? array_values($periods) : [];
        $refs = array_column($periods, 'ref');

        $byTheme = [];
        foreach ($periods as $p) {
            foreach ($p['temas'] ?? [] as $t) {
                $byTheme[$t['chave']][] = ['ref' => $p['ref'], 'sentimento' => $t['sentimento']];
            }
        }

        $label = fn ($k) => mb_strtolower(FeedbackReportSchema::THEMES[$k] ?? $k);
        $sentiment = fn ($s) => mb_strtolower(FeedbackReportSchema::SENTIMENTS[$s] ?? $s);

        $mudancas = [];
        $recorrentes = [];
        foreach ($byTheme as $key => $hits) {
            if (count($hits) >= 2) {
                $recorrentes[] = ['chave' => $key, 'fontes' => array_column($hits, 'ref')];
            }
            $first = $hits[0];
            $last = $hits[count($hits) - 1];
            if (count($hits) >= 2 && $first['sentimento'] !== $last['sentimento']) {
                $mudancas[] = [
                    'texto' => sprintf('O tema %s apareceu como %s em %s e como %s em %s.', $label($key), $sentiment($first['sentimento']), $first['ref'], $sentiment($last['sentimento']), $last['ref']),
                    'fontes' => [$first['ref'], $last['ref']],
                ];
            } elseif (count($hits) >= 2) {
                $mudancas[] = [
                    'texto' => sprintf('O tema %s se manteve como %s nas conversas %s.', $label($key), $sentiment($first['sentimento']), implode(', ', array_column($hits, 'ref'))),
                    'fontes' => array_column($hits, 'ref'),
                ];
            }
        }

        $compromissos = [];
        foreach ($periods as $p) {
            $count = count($p['compromissos'] ?? []);
            if ($count > 0) {
                $compromissos[] = [
                    'texto' => $count === 1
                        ? sprintf('Em %s foi combinado 1 compromisso, registrado no relatório daquela conversa.', $p['ref'])
                        : sprintf('Em %s foram combinados %d compromissos, registrados no relatório daquela conversa.', $p['ref'], $count),
                    'fontes' => [$p['ref']],
                ];
            }
        }

        $text = (string) json_encode($periods, JSON_UNESCAPED_UNICODE);
        $nao = ['Os relatórios não registram se os compromissos de períodos anteriores foram cumpridos.'];
        if (count($refs) < 3) {
            $nao[] = 'Com só dois feedbacks, não dá para falar em tendência.';
        }

        return [
            'resumo' => sprintf(
                'Foram %d conversas de feedback, de %s a %s. Os assuntos mais presentes foram %s. A comparação descreve o que foi conversado e combinado em cada período.',
                count($periods),
                $periods[0]['periodo'] ?? 'sem data',
                $periods[count($periods) - 1]['periodo'] ?? 'sem data',
                $byTheme === [] ? 'gerais' : implode(', ', array_map($label, array_slice(array_keys($byTheme), 0, 4))),
            ),
            'mudancas' => array_slice($mudancas, 0, 8),
            'temas_recorrentes' => array_slice($recorrentes, 0, 8),
            'compromissos_retomados' => array_slice($compromissos, 0, 8),
            'nao_afirmado' => $nao,
            'assunto_sensivel_citado' => (bool) preg_match(FeedbackReportSchema::SENSITIVE_PATTERN, $text),
        ];
    }

    private function sentence(string $text, string $pattern): ?string
    {
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            if (preg_match($pattern, $sentence)) {
                $sentence = trim($sentence);

                return mb_strlen($sentence) > 220 ? rtrim(mb_substr($sentence, 0, 220)) : $sentence;
            }
        }

        return null;
    }
}
