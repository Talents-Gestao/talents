<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Services\Feedback\Ai\FeedbackAiException;
use App\Support\Feedback\Ai\FeedbackAiCostEstimator;
use App\Support\Feedback\Ai\FeedbackReportSchema;
use App\Support\Feedback\TranscriptChunker;
use App\Support\Feedback\TranscriptSpeakers;
use Tests\TestCase;

/**
 * Peças puras da IA de Feedbacks: blocos com sobreposição, rótulos de falantes e papéis, validação do relatório
 * (JSON inválido, taxonomia fixa, trecho que não está na transcrição, assunto sensível) e custo estimado.
 */
class FeedbackTranscriptAiSupportTest extends TestCase
{
    // ── Blocos ──────────────────────────────────────────────────────────

    public function test_chunks_cover_every_line_once_in_order_with_overlap_as_context_only(): void
    {
        $lines = [];
        for ($i = 1; $i <= 120; $i++) {
            $lines[] = sprintf('[00:%02d:%02d] Líder: fala número %d com algum conteúdo para ocupar espaço.', intdiv($i, 60), $i % 60, $i);
        }
        $text = implode("\n", $lines);

        $chunks = TranscriptChunker::chunks($text, 1000, 200);

        $this->assertGreaterThan(3, count($chunks));
        $this->assertSame('', $chunks[0]['context']);
        $this->assertSame($text, implode("\n", array_column($chunks, 'body')), 'cada linha entra uma única vez, na ordem');

        foreach ($chunks as $i => $chunk) {
            $this->assertLessThanOrEqual(1000, mb_strlen($chunk['body']));
            if ($i > 0) {
                $this->assertNotSame('', $chunk['context']);
                $this->assertStringEndsWith($chunk['context'], $chunks[$i - 1]['body'], 'o contexto é o fim do bloco anterior');
                $this->assertGreaterThanOrEqual(200, mb_strlen($chunk['context']));
            }
        }
    }

    public function test_speaker_header_never_ends_a_block_apart_from_its_speech(): void
    {
        $turns = [];
        for ($i = 0; $i < 200; $i++) {
            $turns[] = sprintf("00:%02d:%02d Speaker %d\nOutro ponto: fala %d sobre prazos, entregas e o time, com algumas palavras a mais.", intdiv($i, 60), $i % 60, $i % 2 + 1, $i);
        }
        $text = implode("\n\n", $turns);

        $chunks = TranscriptChunker::chunks($text, 700, 150);

        $this->assertGreaterThan(5, count($chunks));
        foreach ($chunks as $chunk) {
            $lines = explode("\n", $chunk['body']);
            $this->assertTrue(TranscriptChunker::isHeader($lines[0]), 'cada bloco começa por um cabeçalho de fala');
            $this->assertFalse(TranscriptChunker::isHeader((string) end($lines)), 'nenhum bloco termina num cabeçalho sem fala');
        }
        $normalize = fn (string $t) => preg_replace("/\n+/", "\n", $t);
        $this->assertSame($normalize($text), $normalize(implode("\n", array_column($chunks, 'body'))), 'nada some nem se repete');
        $this->assertFalse(TranscriptChunker::isHeader('Outro ponto: o relatório semanal.'));
        $this->assertTrue(TranscriptChunker::isHeader('Speaker 1 00:00:12'));
        $this->assertTrue(TranscriptChunker::isHeader('[00:00:12] Líder'));
    }

    public function test_long_line_without_breaks_is_split_at_sentence_end(): void
    {
        $text = str_repeat('Esta é uma frase de teste. ', 200);

        $chunks = TranscriptChunker::chunks($text, 500, 100);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(500, mb_strlen($chunk['body']));
            $this->assertStringEndsWith('.', $chunk['body']);
        }
        $this->assertSame(trim($text), implode(' ', array_column($chunks, 'body')));
    }

    // ── Falantes ────────────────────────────────────────────────────────

    public function test_labels_are_detected_in_every_plaud_format(): void
    {
        foreach (['plaud-falantes-tempo.txt', 'plaud-falantes-sem-tempo.txt', 'plaud-falantes-tempo-rotulo-antes.txt'] as $fixture) {
            $labels = TranscriptSpeakers::labels((string) file_get_contents(base_path('tests/Fixtures/plaud/'.$fixture)));
            $this->assertSame(['Speaker 1', 'Speaker 2'], array_column($labels, 'label'), $fixture);
            $this->assertStringStartsWith('Bom dia, Larissa', $labels[0]['sample'], $fixture);
        }

        $names = TranscriptSpeakers::labels((string) file_get_contents(base_path('tests/Fixtures/plaud/plaud-nomes-tempo.txt')));
        $this->assertSame(['Marcos', 'Larissa'], array_column($names, 'label'));

        $this->assertSame([], TranscriptSpeakers::labels((string) file_get_contents(base_path('tests/Fixtures/plaud/plaud-sem-falantes-sem-tempo.txt'))));
    }

    public function test_apply_roles_replaces_only_the_mapped_labels(): void
    {
        $text = "00:00:01 Speaker 1\nBom dia.\n\nSpeaker 2: Oi! Speaker 1 chegou?\n[00:00:09] Speaker 3: Com licença.\nSpeaker 4 00:00:12\nTchau.";

        $result = TranscriptSpeakers::applyRoles($text, [
            'Speaker 1' => 'leader',
            'Speaker 2' => 'employee',
            'Speaker 3' => 'other',
            'Speaker 4' => 'other',
        ]);

        $this->assertStringContainsString("00:00:01 Líder\nBom dia.", $result);
        $this->assertStringContainsString('Colaborador: Oi! Speaker 1 chegou?', $result, 'só o rótulo no início da linha muda');
        $this->assertStringContainsString('[00:00:09] Outra pessoa 1: Com licença.', $result);
        $this->assertStringContainsString('Outra pessoa 2 00:00:12', $result);
    }

    // ── Relatório ───────────────────────────────────────────────────────

    private const TRANSCRIPT = "[00:00:01] Líder: Parabéns pelo projeto do novo fluxo, ficou muito bom.\n\n[00:00:09] Colaborador: Eu vou mandar a proposta até sexta-feira.\n\n[00:00:20] Líder: O relatório chegou atrasado três semanas.\n\n[00:00:30] Colaborador: Minha mãe teve um problema de saúde e eu precisei acompanhar.";

    /** @return array<string, mixed> */
    private function validReport(array $overrides = []): array
    {
        return array_merge([
            'resumo' => 'Conversa sobre o novo fluxo e prazos.',
            'pontos_fortes' => [['texto' => 'Entrega do novo fluxo.', 'trecho' => 'Parabéns pelo projeto do novo fluxo, ficou muito bom.']],
            'pontos_atencao' => [['texto' => 'Atraso no relatório.', 'trecho' => 'O relatório chegou atrasado três semanas.']],
            'temas' => [['chave' => 'prazos_organizacao', 'sentimento' => 'atencao']],
            'compromissos' => [['id' => 'c1', 'descricao' => 'Enviar a proposta.', 'responsavel' => 'colaborador', 'prazo' => 'até sexta-feira', 'trecho' => 'Eu vou mandar a proposta até sexta-feira.']],
            'trechos_chave' => [['texto' => 'Parabéns pelo projeto do novo fluxo', 'falante' => 'lider']],
            'nao_afirmado' => ['Não houve data para o próximo alinhamento.'],
            'assunto_sensivel_citado' => false,
        ], $overrides);
    }

    public function test_valid_report_is_normalized_and_kept(): void
    {
        $raw = "```json\n".json_encode($this->validReport() + ['campo_extra' => 'x'], JSON_UNESCAPED_UNICODE)."\n```";

        $report = FeedbackReportSchema::parse($raw, self::TRANSCRIPT);

        $this->assertArrayNotHasKey('campo_extra', $report);
        $this->assertSame('Eu vou mandar a proposta até sexta-feira.', $report['compromissos'][0]['trecho']);
        $this->assertSame('até sexta-feira', $report['compromissos'][0]['prazo']);
        $this->assertCount(1, $report['trechos_chave']);
    }

    public function test_invalid_json_or_schema_is_a_clear_failure(): void
    {
        $cases = [
            'não é JSON',
            '[1, 2, 3]',
            json_encode($this->validReport(['temas' => [['chave' => 'tema_inventado', 'sentimento' => 'positivo']]])),
            json_encode($this->validReport(['pontos_atencao' => [['texto' => 'Sem trecho']]])),
            json_encode($this->validReport(['compromissos' => [['id' => 'x', 'descricao' => 'a', 'responsavel' => 'lider', 'prazo' => null, 'trecho' => 'a']]])),
            json_encode(array_diff_key($this->validReport(), ['nao_afirmado' => 1])),
        ];

        foreach ($cases as $raw) {
            try {
                FeedbackReportSchema::parse((string) $raw, self::TRANSCRIPT);
                $this->fail('Deveria recusar: '.mb_substr((string) $raw, 0, 60));
            } catch (FeedbackAiException $e) {
                $this->assertSame(FeedbackReportSchema::INVALID_MESSAGE, $e->getMessage());
                $this->assertStringStartsWith('invalid_report', $e->errorCode);
            }
        }
    }

    public function test_quote_not_in_transcript_becomes_null_and_invented_key_quote_is_dropped(): void
    {
        $report = FeedbackReportSchema::parse((string) json_encode($this->validReport([
            'pontos_atencao' => [['texto' => 'Atraso.', 'trecho' => 'Ele disse que vai pedir demissão.']],
            'trechos_chave' => [['texto' => 'Frase que ninguém disse.', 'falante' => 'lider']],
        ])), self::TRANSCRIPT);

        $this->assertNull($report['pontos_atencao'][0]['trecho']);
        $this->assertSame([], $report['trechos_chave']);
    }

    public function test_sensitive_details_are_removed_and_only_flagged(): void
    {
        $report = FeedbackReportSchema::parse((string) json_encode($this->validReport([
            'resumo' => 'Conversa sobre prazos. A colaboradora contou que a mãe teve um problema de saúde.',
            'pontos_atencao' => [
                ['texto' => 'Acompanhou a mãe ao hospital.', 'trecho' => 'Minha mãe teve um problema de saúde e eu precisei acompanhar.'],
                ['texto' => 'Atraso no relatório.', 'trecho' => 'O relatório chegou atrasado três semanas.'],
            ],
            'assunto_sensivel_citado' => false,
        ])), self::TRANSCRIPT);

        $this->assertTrue($report['assunto_sensivel_citado']);
        $this->assertSame('Conversa sobre prazos.', $report['resumo']);
        $this->assertCount(1, $report['pontos_atencao']);
        $this->assertStringNotContainsStringIgnoringCase('saúde', (string) json_encode($report, JSON_UNESCAPED_UNICODE));

        foreach (['O filho teve febre.', 'O pai recebeu diagnóstico de diabetes.', 'Faz fisioterapia duas vezes.', 'Pediu adiantamento de salário.', 'Problema na família.'] as $sentence) {
            $this->assertMatchesRegularExpression(FeedbackReportSchema::SENSITIVE_PATTERN, $sentence, $sentence);
        }
        foreach (['Combinamos revisar os prazos.', 'O relatório chegou atrasado.', 'Parabéns pelo projeto.'] as $sentence) {
            $this->assertDoesNotMatchRegularExpression(FeedbackReportSchema::SENSITIVE_PATTERN, $sentence, $sentence);
        }
    }

    public function test_cost_estimate_is_in_cents_and_zero_for_demo(): void
    {
        $this->assertSame(0, FeedbackAiCostEstimator::cents('demo', 'demonstracao-local', 100_000, 100_000));
        // gpt-4o-mini: 1M entrada (US$ 0,15) + 1M saída (US$ 0,60) = US$ 0,75 × 5,50 = R$ 4,125 → 413 centavos
        $this->assertSame(413, FeedbackAiCostEstimator::cents('openai', 'gpt-4o-mini', 1_000_000, 1_000_000));
        // modelo desconhecido: faixa mais cara conhecida
        $this->assertGreaterThan(
            FeedbackAiCostEstimator::cents('anthropic', 'claude-sonnet-4-5', 10_000, 10_000),
            FeedbackAiCostEstimator::cents('anthropic', 'modelo-novo', 10_000, 10_000),
        );
    }
}
