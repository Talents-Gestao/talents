<?php

declare(strict_types=1);

namespace App\Support\Feedback\Ai;

use App\Services\Feedback\Ai\FeedbackAiException;
use Illuminate\Support\Facades\Validator;

/**
 * Esquema da narrativa de Evolução (JSON devolvido pela IA), validado no servidor. Toda afirmação cita as sessões
 * de origem («S1», «S2»…); afirmação sem fonte válida é descartada. O servidor também retira frases com assunto
 * sensível (só sinaliza) e com julgamento sobre a pessoa (saúde, desempenho definitivo, desligamento, promoção),
 * e não aceita «tendência» com menos de três sessões.
 */
final class FeedbackEvolutionSchema
{
    public const VERSION = 'feedback_evolution.v1';

    public const INVALID_MESSAGE = 'A IA devolveu a evolução num formato inválido. Tente gerar de novo.';

    /** Julgamento sobre a pessoa ou decisão de RH: nunca entra no texto. */
    public const JUDGMENT_PATTERN = '/\b(deslig\w*|demiss\w*|demit\w*|justa causa|dispens\w*|promo[cç][aã]o|promovid\w*|promover|incompet\w*|pregui[cç]\w*|baixo desempenho|alto desempenho|mau desempenho|desempenho (?:ruim|fraco|excelente|insuficiente)|não serve|deve ser substitu\w*|nota \d+|avalia[cç][aã]o final)\b/iu';

    public static function promptDescription(): string
    {
        $themes = implode(', ', array_keys(FeedbackReportSchema::THEMES));

        return <<<TXT
{
  "resumo": "string (até 1200 caracteres): o que foi conversado e combinado ao longo dos feedbacks, em ordem",
  "mudancas": [{"texto": "string (até 300): o que mudou ou se manteve entre os períodos", "fontes": ["S1", "S2"]}],
  "temas_recorrentes": [{"chave": "uma de: {$themes}", "fontes": ["S1", "S3"]}],
  "compromissos_retomados": [{"texto": "string (até 300): compromisso citado em mais de uma conversa ou retomado depois", "fontes": ["S2"]}],
  "nao_afirmado": ["string (até 300): o que não pode ser concluído a partir dos relatórios"],
  "assunto_sensivel_citado": true ou false
}
Limites: até 8 itens em cada lista. Toda frase de mudancas, temas_recorrentes e compromissos_retomados precisa de pelo menos uma fonte válida (temas_recorrentes, pelo menos duas).
TXT;
    }

    /**
     * @param  list<string>  $refs  referências válidas, na ordem dos períodos («S1», «S2»…)
     * @return array<string, mixed>
     *
     * @throws FeedbackAiException
     */
    public static function parse(string $raw, array $refs): array
    {
        $raw = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $data = ($start === false || $end === false) ? null : json_decode(substr($raw, $start, $end - $start + 1), true);
        if (! is_array($data) || array_is_list($data)) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_evolution: json');
        }

        $validator = Validator::make($data, [
            'resumo' => ['required', 'string', 'max:1200'],
            'mudancas' => ['present', 'array', 'max:8'],
            'mudancas.*.texto' => ['required', 'string', 'max:300'],
            'mudancas.*.fontes' => ['required', 'array', 'min:1'],
            'mudancas.*.fontes.*' => ['string'],
            'temas_recorrentes' => ['present', 'array', 'max:8'],
            'temas_recorrentes.*.chave' => ['required', 'string', 'in:'.implode(',', array_keys(FeedbackReportSchema::THEMES))],
            'temas_recorrentes.*.fontes' => ['required', 'array', 'min:1'],
            'temas_recorrentes.*.fontes.*' => ['string'],
            'compromissos_retomados' => ['present', 'array', 'max:8'],
            'compromissos_retomados.*.texto' => ['required', 'string', 'max:300'],
            'compromissos_retomados.*.fontes' => ['required', 'array', 'min:1'],
            'compromissos_retomados.*.fontes.*' => ['string'],
            'nao_afirmado' => ['present', 'array', 'max:8'],
            'nao_afirmado.*' => ['required', 'string', 'max:300'],
            'assunto_sensivel_citado' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            throw new FeedbackAiException(self::INVALID_MESSAGE, 'invalid_evolution: '.mb_substr((string) $validator->errors()->keys()[0], 0, 40));
        }

        $sensitive = (bool) $data['assunto_sensivel_citado'];
        $fewPoints = count($refs) < 3;
        $blocked = function (string $text) use (&$sensitive, $fewPoints): bool {
            if (preg_match(FeedbackReportSchema::SENSITIVE_PATTERN, $text)) {
                $sensitive = true;

                return true;
            }

            return (bool) preg_match(self::JUDGMENT_PATTERN, $text)
                || ($fewPoints && preg_match('/\btend[eê]ncia/iu', $text));
        };
        $sources = fn (array $fontes) => array_values(array_unique(array_filter(
            array_map(fn ($f) => strtoupper(trim((string) $f)), $fontes),
            fn ($f) => in_array($f, $refs, true),
        )));

        $items = function (array $list, int $minSources) use ($sources, $blocked): array {
            $out = [];
            foreach ($list as $item) {
                $fontes = $sources($item['fontes']);
                $texto = trim((string) ($item['texto'] ?? ''));
                if (count($fontes) < $minSources || ($texto !== '' && $blocked($texto))) {
                    continue; // sem fonte válida, ou com conteúdo que não pode aparecer
                }
                $out[] = $texto !== '' ? ['texto' => $texto, 'fontes' => $fontes] : ['chave' => $item['chave'], 'fontes' => $fontes];
            }

            return $out;
        };

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim((string) $data['resumo'])) ?: [];
        $resumo = trim(implode(' ', array_filter($sentences, fn ($s) => trim($s) !== '' && ! $blocked($s))));

        $result = [
            'resumo' => $resumo !== '' ? $resumo : 'Resumo omitido: o texto gerado tratava de assunto que não pode aparecer aqui.',
            'mudancas' => $items($data['mudancas'], 1),
            'temas_recorrentes' => $items(array_map(fn ($t) => ['chave' => $t['chave'], 'fontes' => $t['fontes']], $data['temas_recorrentes']), 2),
            'compromissos_retomados' => $items($data['compromissos_retomados'], 1),
            // Ressalvas podem citar «tendência» (ex.: «não dá para falar em tendência com dois feedbacks»).
            'nao_afirmado' => array_values(array_filter(array_map('trim', $data['nao_afirmado']), function ($n) use (&$sensitive) {
                if ($n === '' || preg_match(self::JUDGMENT_PATTERN, $n)) {
                    return false;
                }
                if (preg_match(FeedbackReportSchema::SENSITIVE_PATTERN, $n)) {
                    $sensitive = true;

                    return false;
                }

                return true;
            })),
        ];
        $result['assunto_sensivel_citado'] = $sensitive;

        return $result;
    }
}
