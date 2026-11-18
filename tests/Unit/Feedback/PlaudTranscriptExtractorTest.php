<?php

declare(strict_types=1);

namespace Tests\Unit\Feedback;

use App\Services\Feedback\PlaudTranscriptExtractor;
use App\Services\Feedback\TranscriptExtractionException;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Leitura da transcrição exportada do Plaud: TXT, SRT, DOCX e PDF, sem limpar nem inventar texto.
 */
class PlaudTranscriptExtractorTest extends TestCase
{
    private PlaudTranscriptExtractor $extractor;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new PlaudTranscriptExtractor;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function temp(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'plaud');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_reads_utf8_txt_keeping_speakers_and_accents(): void
    {
        $result = $this->extractor->extract(base_path('tests/Fixtures/plaud/plaud-falantes-tempo-rotulo-antes.txt'), 'Feedback Larissa.TXT');

        $this->assertSame('txt', $result['format']);
        $this->assertStringStartsWith('Speaker 1 00:00:01', $result['text']);
        $this->assertStringContainsString('Ótimo. Combinamos de revisar os prazos até sexta-feira.', $result['text']);
    }

    public function test_txt_in_windows_1252_or_utf16_is_converted_to_utf8(): void
    {
        $latin = $this->temp(mb_convert_encoding("Não há prazo.\r\nAção combinada.", 'Windows-1252', 'UTF-8'));
        $this->assertSame("Não há prazo.\nAção combinada.", $this->extractor->extract($latin, 'a.txt')['text']);

        $utf16 = $this->temp("\xFF\xFE".mb_convert_encoding('Reunião de líderes', 'UTF-16LE', 'UTF-8'));
        $this->assertSame('Reunião de líderes', $this->extractor->extract($utf16, 'a.txt')['text']);

        $bom = $this->temp("\xEF\xBB\xBFOlá");
        $this->assertSame('Olá', $this->extractor->extract($bom, 'a.txt')['text']);
    }

    public function test_reads_srt_as_timestamped_lines(): void
    {
        $result = $this->extractor->extract(base_path('tests/Fixtures/plaud/plaud-falantes.srt'), 'transcricao.srt');

        $this->assertSame('srt', $result['format']);
        $this->assertSame(implode("\n", [
            '[00:00:01] Speaker 1: Bom dia, Larissa. Vamos começar o alinhamento do semestre?',
            '[00:00:06] Speaker 2: Bom dia! Sim, eu trouxe as entregas do projeto de atendimento.',
            '[00:01:12] Speaker 1: Combinamos de revisar os prazos até sexta-feira.',
        ]), $result['text']);
    }

    /**
     * Exemplos no formato de exportação do Plaud (fixtures em tests/Fixtures/plaud; trocar por exportações reais).
     *
     * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
     */
    public static function plaudExports(): array
    {
        return [
            'TXT falantes + tempo (tempo antes)' => ['plaud-falantes-tempo.txt', 'txt', true, true],
            'TXT falantes + tempo (rótulo antes)' => ['plaud-falantes-tempo-rotulo-antes.txt', 'txt', true, true],
            'TXT nomes renomeados + tempo' => ['plaud-nomes-tempo.txt', 'txt', true, true],
            'TXT falantes sem tempo' => ['plaud-falantes-sem-tempo.txt', 'txt', true, false],
            'TXT sem falantes com tempo' => ['plaud-sem-falantes-tempo.txt', 'txt', false, true],
            'TXT sem falantes sem tempo' => ['plaud-sem-falantes-sem-tempo.txt', 'txt', false, false],
            'SRT com falantes' => ['plaud-falantes.srt', 'srt', true, true],
            'SRT sem falantes' => ['plaud-sem-falantes.srt', 'srt', false, true],
            'DOCX falantes + tempo' => ['plaud-falantes-tempo.docx', 'docx', true, true],
            'DOCX sem falantes sem tempo' => ['plaud-sem-falantes-sem-tempo.docx', 'docx', false, false],
        ];
    }

    #[DataProvider('plaudExports')]
    public function test_detects_speakers_and_timestamps_in_plaud_exports(string $fixture, string $format, bool $speakers, bool $timestamps): void
    {
        $result = $this->extractor->extract(base_path('tests/Fixtures/plaud/'.$fixture), $fixture);

        $this->assertSame($format, $result['format']);
        $this->assertSame($speakers, $result['has_speakers'], 'Identificação de quem fala');
        $this->assertSame($timestamps, $result['has_timestamps'], 'Marcação de tempo');
        $this->assertStringContainsString('Larissa', $result['text']);
    }

    public function test_a_single_label_like_line_is_not_taken_as_speakers(): void
    {
        $text = "Obs: revisar os prazos.\nTexto corrido sem rótulos.\nSpeaker 1 aparece só uma vez.";

        $this->assertFalse($this->extractor->hasSpeakerLabels($text));
        $this->assertFalse($this->extractor->hasTimestamps('A reunião é às 10:30 de quinta.'));
    }

    public function test_srt_without_timestamps_is_refused(): void
    {
        $this->expectException(TranscriptExtractionException::class);
        $this->expectExceptionMessage('Não encontramos marcações de tempo no SRT.');

        $this->extractor->extract($this->temp("Só texto\nsem tempo"), 'a.srt');
    }

    public function test_reads_docx_paragraphs_and_tables(): void
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('Speaker 1 00:00:01');
        $run = $section->addTextRun();
        $run->addText('Bom dia, ');
        $run->addText('Larissa.', ['bold' => true]);
        $section->addTextBreak();
        $table = $section->addTable();
        $table->addRow();
        $table->addCell()->addText('Speaker 2');
        $table->addCell()->addText('Trouxe as entregas.');

        $path = (string) tempnam(sys_get_temp_dir(), 'plaud').'.docx';
        $this->tempFiles[] = $path;
        IOFactory::createWriter($word, 'Word2007')->save($path);

        $result = $this->extractor->extract($path, 'transcricao.docx');

        $this->assertSame('docx', $result['format']);
        $this->assertStringContainsString("Speaker 1 00:00:01\nBom dia, Larissa.", $result['text']);
        $this->assertStringContainsString('Speaker 2 | Trouxe as entregas.', $result['text']);
    }

    public function test_corrupted_docx_is_refused(): void
    {
        $this->expectException(TranscriptExtractionException::class);
        $this->expectExceptionMessage('O arquivo DOCX está corrompido ou não é um documento do Word.');

        $this->extractor->extract($this->temp('não é zip'), 'a.docx');
    }

    public function test_reads_pdf_with_the_container_extractor(): void
    {
        $python = new Process(['python3', '-c', 'import pdfplumber']);
        $python->run();
        if (! $python->isSuccessful()) {
            $this->markTestSkipped('python3 com pdfplumber indisponível neste ambiente.');
        }

        $path = $this->temp(Pdf::loadHTML('<p>Speaker 1: Combinamos revisar os prazos.</p>')->output());

        $result = $this->extractor->extract($path, 'transcricao.pdf');

        $this->assertSame('pdf', $result['format']);
        $this->assertStringContainsString('Speaker 1: Combinamos revisar os prazos.', $result['text']);
    }

    public function test_fake_pdf_is_refused(): void
    {
        $this->expectException(TranscriptExtractionException::class);
        $this->expectExceptionMessage('O arquivo PDF está corrompido ou não é um PDF.');

        $this->extractor->extract($this->temp('texto qualquer'), 'a.pdf');
    }

    public function test_empty_binary_and_unsupported_files_are_refused(): void
    {
        $cases = [
            [$this->temp(''), 'a.txt', 'O arquivo está vazio.'],
            [$this->temp("   \n\n  "), 'a.txt', 'Não encontramos texto no arquivo.'],
            [$this->temp("ID3\0\0binario"), 'a.txt', 'O arquivo não parece ser texto.'],
            [$this->temp('áudio'), 'a.mp3', 'Formato não aceito. Envie TXT, SRT, DOCX ou PDF.'],
        ];

        foreach ($cases as [$path, $name, $message]) {
            try {
                $this->extractor->extract($path, $name);
                $this->fail("Deveria recusar {$name}.");
            } catch (TranscriptExtractionException $e) {
                $this->assertStringStartsWith($message, $e->getMessage());
            }
        }
    }
}
