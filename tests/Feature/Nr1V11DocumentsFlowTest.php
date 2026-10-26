<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Exceptions\PsychosocialDocumentException;
use App\Models\ApproverOrganization;
use App\Models\DocumentShare;
use App\Models\DocumentShareReview;
use App\Models\PsychosocialDocument;
use App\Models\PsychosocialDocumentVersion;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialReviewAcknowledgement;
use App\Models\PsychosocialReviewEvent;
use App\Models\PsychosocialReviewRound;
use App\Models\Survey;
use App\Models\SurveyCampaignRegistration;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\Psychosocial\DocumentPublicationChecklist;
use App\Services\Psychosocial\PsychosocialDocumentService;
use App\Services\Psychosocial\ReviewRoundService;
use App\Services\PsychosocialMeasureService;
use App\Support\CanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Support\CreatesSurveyFixtures;
use Tests\Support\CreatesV11Campaign;
use Tests\TestCase;

/**
 * Etapa 7: fluxo de documentos (Caderno §12 e §13). Dois documentos finais, revisados e publicados juntos:
 * Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado.
 * Cobre T02, T03, T04, T18, T19, T20, ciência da empresa, integração ao PGR e autorização por perfil.
 */
class Nr1V11DocumentsFlowTest extends TestCase
{
    use CreatesSurveyFixtures;
    use CreatesV11Campaign;
    use RefreshDatabase;

    private ReviewRoundService $rounds;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PsychosocialDocumentService::DISK);
        config(['nr1.external_review.enabled' => true, 'nr1.external_review.mfa_required' => false]);
        $this->rounds = app(ReviewRoundService::class);
    }

    private function registration(Survey $survey, array $overrides = []): SurveyCampaignRegistration
    {
        return SurveyCampaignRegistration::query()->updateOrCreate(['survey_id' => $survey->id], array_merge([
            'company_id' => $survey->company_id,
            'legal_name_snapshot' => 'Empresa V11 Ltda',
            'cnpj_snapshot' => '11.222.333/0001-81',
            'unit_name' => 'Unidade Centro',
            'eligible_count' => 48,
            'eligible_source' => 'Cadastro de RH de outubro de 2026',
            'technical_responsible_name' => 'Carla Souza',
            'technical_responsible_role' => 'Psicóloga do trabalho',
            'technical_responsible_registration' => 'CRP 06/123456',
            'approving_organization_name' => 'Organização aprovadora',
        ], $overrides));
    }

    /**
     * Campanha V11 que exige publicação, com cadastro técnico completo e uma medida no plano.
     *
     * @return object{company: mixed, survey: Survey, talents: User, reviewer: User, admin: User, measure: ?PsychosocialMeasure}
     */
    private function flow(bool $withRegistration = true, bool $withMeasure = true): object
    {
        $campaign = $this->v11Campaign();
        $campaign->survey->forceFill(['requires_publication' => true, 'published_at' => null])->save();
        $survey = $campaign->survey->fresh();

        $talents = User::factory()->superAdmin()->create(['is_owner' => true, 'name' => 'Talents Owner']);
        $organization = ApproverOrganization::query()->create(['name' => 'Organização aprovadora', 'is_active' => true]);
        $reviewer = User::factory()->externalReviewer($organization->id)->create(['name' => 'Revisora PASSEG']);
        $admin = User::factory()->companyAdmin($campaign->company->id)->create(['name' => 'Ana Admin']);

        if ($withRegistration) {
            $this->registration($survey);
        }

        $measure = $withMeasure
            ? app(PsychosocialMeasureService::class)->createManual($survey, [
                'factor_label' => 'Demandas e Sobrecarga',
                'evidence_basis' => PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION,
                'measure_description' => 'Revisar a distribuição das tarefas do setor.',
                'responsible_text' => 'Gestão de operações',
                'suggested_due_date' => now()->addDays(30)->toDateString(),
            ], $talents)
            : null;

        return (object) (compact('campaign', 'survey', 'talents', 'reviewer', 'admin', 'measure') + ['company' => $campaign->company]);
    }

    private function sent(object $flow): PsychosocialReviewRound
    {
        $round = $this->rounds->start($flow->survey, $flow->talents);
        $round = $this->rounds->regenerate($round, $flow->talents);

        return $this->rounds->send($round, $flow->talents, $flow->reviewer);
    }

    private function published(object $flow): PsychosocialReviewRound
    {
        $round = $this->rounds->approve($this->sent($flow), $flow->reviewer);

        return $this->rounds->publish($round, $flow->talents);
    }

    public function test_full_cycle_with_change_request_new_version_approval_and_publication(): void
    {
        $flow = $this->flow();

        // Iniciar revisão e gerar os dois documentos (snapshot congelado + PDF com hash).
        $round = $this->rounds->start($flow->survey, $flow->talents);
        $this->assertSame(PsychosocialReviewRound::STATUS_DRAFT, $round->status);
        $this->assertSame('Em edição', $round->statusLabel());

        $round = $this->rounds->regenerate($round, $flow->talents);
        $executive = $round->executiveVersion;
        $technical = $round->technicalVersion;
        $this->assertSame(PsychosocialDocument::TYPE_EXECUTIVE_BRIEF, $executive->document->type);
        $this->assertSame(PsychosocialDocument::TYPE_TECHNICAL_REPORT, $technical->document->type);
        foreach ([$executive, $technical] as $version) {
            $this->assertSame(PsychosocialDocumentVersion::PDF_READY, $version->pdf_status);
            $this->assertSame($version->snapshot_sha256, CanonicalJson::sha256($version->snapshot), 'Snapshot congelado com hash.');
            $bytes = Storage::disk(PsychosocialDocumentService::DISK)->get($version->pdf_path);
            $this->assertSame($version->pdf_sha256, hash('sha256', $bytes), 'PDF gravado confere com o hash.');
        }

        // Enviar à PASSEG: os dois documentos vão juntos.
        $round = $this->rounds->send($round, $flow->talents, $flow->reviewer);
        $this->assertSame('Em revisão PASSEG', $round->statusLabel());
        $this->assertSame(2, DocumentShare::query()->where('reviewer_id', $flow->reviewer->id)->count());

        // T04: solicitar alteração exige texto e mantém histórico.
        try {
            $this->rounds->requestChanges($round, $flow->reviewer, 'curto');
            $this->fail('Texto curto demais deveria ser recusado.');
        } catch (PsychosocialDocumentException) {
        }
        $text = 'Detalhar o responsável técnico no controle documental.';
        $round = $this->rounds->requestChanges($round, $flow->reviewer, $text);
        $this->assertSame('Alteração solicitada', $round->statusLabel());
        $this->assertSame(1, DocumentShareReview::query()->where('kind', DocumentShareReview::KIND_CHANGE_REQUEST)->count());

        // Reenviar sem gerar nova versão: a mudança exige nova versão.
        try {
            $this->rounds->send($round, $flow->talents, $flow->reviewer);
            $this->fail('Reenvio sem nova versão deveria ser recusado.');
        } catch (PsychosocialDocumentException $exception) {
            $this->assertStringContainsString('nova versão', $exception->getMessage());
        }

        // Nova versão: as anteriores ficam preservadas.
        $round = $this->rounds->regenerate($round, $flow->talents);
        $this->assertSame(2, $round->technicalVersion->version_number);
        $this->assertSame(2, PsychosocialDocumentVersion::query()->where('document_id', $technical->document_id)->count());
        $this->assertSame($technical->content_sha256, $technical->fresh()->content_sha256, 'A versão 1 não muda.');

        $round = $this->rounds->send($round, $flow->talents, $flow->reviewer);

        // T03: aprova só o que foi enviado; depois de aprovado, não aprova de novo.
        $round = $this->rounds->approve($round, $flow->reviewer);
        $this->assertSame('Aprovado pela PASSEG', $round->statusLabel());
        $this->assertSame($flow->reviewer->id, (int) $round->approved_by);
        try {
            $this->rounds->approve($round, $flow->reviewer);
            $this->fail('Aprovar duas vezes deveria ser recusado.');
        } catch (PsychosocialDocumentException) {
        }

        // Publicar define o "Publicado" do gate, com data, responsável e protocolo.
        $this->assertNull($flow->survey->fresh()->published_at);
        $round = $this->rounds->publish($round, $flow->talents);
        $this->assertSame('Publicado', $round->statusLabel());
        $this->assertSame($flow->talents->id, (int) $round->published_by);
        $this->assertMatchesRegularExpression('/^NR1-\d+-R01-\d{8}-[A-Z0-9]{6}$/', (string) $round->protocol);
        $survey = $flow->survey->fresh();
        $this->assertNotNull($survey->published_at);
        $this->assertSame($flow->talents->id, (int) $survey->published_by);

        // Histórico com autor, data e justificativa, na ordem.
        $history = $this->rounds->history($round);
        $this->assertSame(
            ['created', 'regenerated', 'sent', 'change_requested', 'regenerated', 'sent', 'approved', 'published'],
            array_column($history, 'action'),
        );
        $request = collect($history)->firstWhere('action', 'change_requested');
        $this->assertSame($text, $request['body']);
        $this->assertSame('Revisora PASSEG', $request['author']);
        $this->assertNotSame('', $request['at']);
    }

    public function test_t02_company_sees_nothing_before_published_and_only_the_published_round_after(): void
    {
        $flow = $this->flow();
        $round = $this->sent($flow);
        $executiveV1 = $round->executiveVersion;

        // Antes de "Publicado": 404 em tudo que é da empresa, inclusive os downloads dos documentos.
        foreach ([
            route('client.surveys.documents.index', $flow->survey),
            route('client.surveys.psychosocial-documents.download', [$flow->survey, 'executive_brief']),
            route('client.surveys.psychosocial-documents.download', [$flow->survey, 'technical_report']),
            route('client.surveys.executive-brief.download', $flow->survey),
        ] as $url) {
            $this->actingAs($flow->admin)->get($url)->assertNotFound();
        }

        $round = $this->rounds->publish($this->rounds->approve($round, $flow->reviewer), $flow->talents);

        $this->actingAs($flow->admin)->get(route('client.surveys.documents.index', $flow->survey))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Surveys/Documents')
                ->where('round.status_label', 'Publicado')
                ->where('round.protocol', $round->protocol)
                ->has('round.versions', 2));
        $this->actingAs($flow->admin)->get(route('client.surveys.psychosocial-documents.download', [$flow->survey, 'technical_report']))->assertOk();
        $this->actingAs($flow->admin)->get(route('client.surveys.executive-brief.download', $flow->survey))->assertOk();

        // Nova revisão em andamento: a empresa continua com a publicada; o rascunho não vaza.
        $second = $this->rounds->start($flow->survey->fresh(), $flow->talents);
        $second = $this->rounds->regenerate($second, $flow->talents);
        $this->assertNotSame($round->executive_version_id, $second->executive_version_id);

        $response = $this->actingAs($flow->admin)->get(route('client.surveys.executive-brief.download', $flow->survey))->assertOk();
        $this->assertStringContainsString($executiveV1->public_identifier, (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString($second->executiveVersion->public_identifier, (string) $response->headers->get('Content-Disposition'));
    }

    public function test_t03_reviewer_acts_only_on_what_was_shared_and_submitted(): void
    {
        $flow = $this->flow();
        $other = User::factory()->externalReviewer(ApproverOrganization::query()->firstOrFail()->id)->create();

        $round = $this->rounds->start($flow->survey, $flow->talents);
        $round = $this->rounds->regenerate($round, $flow->talents);

        // Rascunho (não enviado): nem o revisor com acesso aprova.
        foreach ([$flow->reviewer, $other] as $user) {
            try {
                $this->rounds->approve($round, $user);
                $this->fail('Ninguém aprova uma revisão que não foi enviada.');
            } catch (PsychosocialDocumentException) {
            }
        }

        $round = $this->rounds->send($round, $flow->talents, $flow->reviewer);

        // Revisor sem compartilhamento: não aprova nem pede alteração; por HTTP recebe 404.
        try {
            $this->rounds->approve($round, $other);
            $this->fail('Revisor sem compartilhamento não aprova.');
        } catch (PsychosocialDocumentException) {
        }
        $versionId = $round->technical_version_id;
        $this->actingAs($other)->post(route('external-review.approve', $versionId))->assertNotFound();
        $this->actingAs($other)->post(route('external-review.request-changes', $versionId), ['body' => 'Texto suficiente para pedir ajuste.'])->assertNotFound();
        $this->assertSame(PsychosocialReviewRound::STATUS_IN_REVIEW, $round->fresh()->status);

        // Revisor certo, por HTTP: aprovar muda a rodada.
        $this->actingAs($flow->reviewer)->post(route('external-review.approve', $versionId))->assertSessionHas('success');
        $this->assertSame(PsychosocialReviewRound::STATUS_APPROVED, $round->fresh()->status);

        // Mudança depois de aprovado exige nova revisão: a rodada aprovada não aceita mais "gerar".
        try {
            $this->rounds->regenerate($round->fresh(), $flow->talents);
            $this->fail('Rodada aprovada não regenera documentos.');
        } catch (PsychosocialDocumentException) {
        }
    }

    public function test_t04_change_request_over_http_needs_text_and_keeps_the_history(): void
    {
        $flow = $this->flow();
        $round = $this->sent($flow);
        $url = route('external-review.request-changes', $round->technical_version_id);

        $this->actingAs($flow->reviewer)->post($url, ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($flow->reviewer)->post($url, ['body' => 'curto'])->assertSessionHasErrors('body');
        $this->assertSame(PsychosocialReviewRound::STATUS_IN_REVIEW, $round->fresh()->status);

        $this->actingAs($flow->reviewer)->post($url, ['body' => 'Incluir o registro profissional completo.'])->assertSessionHas('success');
        $round = $round->fresh();
        $this->assertSame(PsychosocialReviewRound::STATUS_CHANGES_REQUESTED, $round->status);

        $event = PsychosocialReviewEvent::query()->where('round_id', $round->id)->where('action', 'change_requested')->sole();
        $this->assertSame('Incluir o registro profissional completo.', $event->body);
        $this->assertSame($flow->reviewer->id, (int) $event->user_id);
        $this->assertSame('in_review', $event->from_status);
        $this->assertSame('changes_requested', $event->to_status);
    }

    public function test_t19_publication_is_blocked_by_empty_or_fictitious_fields_and_names_the_pending_items(): void
    {
        $flow = $this->flow(withRegistration: false);
        $round = $this->rounds->start($flow->survey, $flow->talents);
        $round = $this->rounds->regenerate($round, $flow->talents);

        $labels = array_column($this->rounds->pending($round), 'label');
        foreach (['CNPJ', 'Razão social', 'Estabelecimento', 'Total de elegíveis', 'Responsável técnico (nome)', 'Responsável técnico (função)', 'Responsável técnico (registro profissional)'] as $expected) {
            $this->assertContains($expected, $labels, "Pendência «{$expected}» não indicada.");
        }

        try {
            $this->rounds->send($round, $flow->talents, $flow->reviewer);
            $this->fail('Enviar com pendências deveria ser recusado.');
        } catch (PsychosocialDocumentException $exception) {
            $this->assertStringContainsString('CNPJ', $exception->getMessage());
            $this->assertStringContainsString('Cadastro técnico da campanha', $exception->getMessage());
        }
        $this->assertSame(0, DocumentShare::query()->count(), 'Nada é compartilhado com pendência.');

        // Fictício e inválido também bloqueiam: marcador de lugar, CNPJ com dígito errado, elegíveis menor que respostas.
        $this->registration($flow->survey, [
            'cnpj_snapshot' => '11.111.111/0001-11',
            'technical_responsible_name' => 'A confirmar',
            'unit_name' => 'Não informado',
            'eligible_count' => 30,
            'eligible_source' => 'Valor fictício da demonstração',
        ]);
        $round = $this->rounds->regenerate($round, $flow->talents);
        $problems = collect($this->rounds->pending($round))->pluck('problem', 'label');
        $this->assertSame('inválido', $problems['CNPJ']);
        $this->assertSame('fictício ou marcador de lugar', $problems['Responsável técnico (nome)']);
        $this->assertSame('fictício ou marcador de lugar', $problems['Estabelecimento']);
        $this->assertSame('fictício ou marcador de lugar', $problems['Fonte do número de elegíveis']);
        $this->assertSame('menor que o número de respostas', $problems['Total de elegíveis']);

        // A checagem olha o SNAPSHOT congelado: corrigir o cadastro não basta, é preciso gerar nova versão.
        $this->registration($flow->survey);
        $this->assertNotEmpty($this->rounds->pending($round->fresh()), 'Cadastro corrigido, mas o documento congelado ainda tem o erro.');

        $round = $this->rounds->regenerate($round->fresh(), $flow->talents);
        $this->assertSame([], $this->rounds->pending($round));
        $this->assertSame(PsychosocialReviewRound::STATUS_IN_REVIEW, $this->rounds->send($round, $flow->talents, $flow->reviewer)->status);
    }

    public function test_t19_publish_checks_the_frozen_snapshots_again(): void
    {
        $flow = $this->flow(withRegistration: false);
        $round = $this->rounds->start($flow->survey, $flow->talents);
        $round = $this->rounds->regenerate($round, $flow->talents);

        // Rodada aprovada à força, com versões que têm campos vazios: a publicação ainda recusa.
        $round->fill(['status' => PsychosocialReviewRound::STATUS_IN_REVIEW])->save();
        $round->fill(['status' => PsychosocialReviewRound::STATUS_APPROVED])->save();

        try {
            $this->rounds->publish($round->fresh(), $flow->talents);
            $this->fail('Publicar com campos vazios deveria ser recusado.');
        } catch (PsychosocialDocumentException $exception) {
            $this->assertStringContainsString('Não é possível publicar', $exception->getMessage());
            $this->assertStringContainsString('CNPJ', $exception->getMessage());
        }
        $this->assertSame(PsychosocialReviewRound::STATUS_APPROVED, $round->fresh()->status);
        $this->assertNull($flow->survey->fresh()->published_at);
    }

    public function test_snapshot_hash_survives_the_json_round_trip_even_with_whole_decimals(): void
    {
        $snapshot = ['bar_percent' => 39.0, 'favorable_pct' => 92.5, 'n' => 40, 'nested' => ['pct' => 75.0, 'list' => [100.0, 2.5]]];
        $roundTrip = json_decode(json_encode($snapshot), true);

        $this->assertSame(CanonicalJson::sha256($snapshot), CanonicalJson::sha256($roundTrip));
        $this->assertSame('{"bar_percent":39,"favorable_pct":92.5,"n":40,"nested":{"list":[100,2.5],"pct":75}}', CanonicalJson::encode($snapshot));
    }

    public function test_checklist_helpers_recognise_valid_cnpj_and_placeholders(): void
    {
        $this->assertTrue(DocumentPublicationChecklist::validCnpj('11.222.333/0001-81'));
        $this->assertFalse(DocumentPublicationChecklist::validCnpj('11.222.333/0001-82'));
        $this->assertFalse(DocumentPublicationChecklist::validCnpj('00.000.000/0000-00'));
        $this->assertFalse(DocumentPublicationChecklist::validCnpj('11111111111111'));
        foreach (['Não informado', 'a confirmar', 'Exemplo', 'XXX', '48 (valor fictício)', 'Lorem ipsum'] as $value) {
            $this->assertTrue(DocumentPublicationChecklist::looksFictitious($value), $value);
        }
        $this->assertFalse(DocumentPublicationChecklist::looksFictitious('Carla Souza'));
    }

    public function test_t20_published_round_never_changes_and_a_new_revision_is_a_new_round(): void
    {
        $flow = $this->flow();
        $round = $this->published($flow);
        $executive = $round->executiveVersion;
        $hashes = [$executive->content_sha256, $round->technicalVersion->content_sha256, $executive->pdf_sha256];

        // A rodada publicada é imutável, inclusive por quem tem o model na mão.
        foreach ([
            fn () => $round->fresh()->fill(['protocol' => 'OUTRO'])->save(),
            fn () => $round->fresh()->fill(['status' => PsychosocialReviewRound::STATUS_DRAFT])->save(),
            fn () => $round->fresh()->fill(['technical_version_id' => $executive->id])->save(),
            fn () => $round->fresh()->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Rodada publicada foi alterada.');
            } catch (LogicException) {
            }
        }

        // Não regenera nem reenvia a publicada.
        try {
            $this->rounds->regenerate($round->fresh(), $flow->talents);
            $this->fail('Regenerar rodada publicada.');
        } catch (PsychosocialDocumentException) {
        }
        try {
            $this->rounds->send($round->fresh(), $flow->talents, $flow->reviewer);
            $this->fail('Reenviar rodada publicada.');
        } catch (PsychosocialDocumentException) {
        }

        // Versões publicadas não mudam (a própria versão é só-inserção).
        try {
            $executive->fresh()->update(['content_sha256' => str_repeat('a', 64)]);
            $this->fail('Versão publicada foi alterada.');
        } catch (LogicException) {
        }

        // Iniciar nova revisão cria a rodada 2; só uma aberta por vez.
        $second = $this->rounds->start($flow->survey->fresh(), $flow->talents);
        $this->assertSame(2, $second->round_number);
        $this->assertSame(PsychosocialReviewRound::STATUS_DRAFT, $second->status);
        try {
            $this->rounds->start($flow->survey->fresh(), $flow->talents);
            $this->fail('Duas revisões abertas ao mesmo tempo.');
        } catch (PsychosocialDocumentException) {
        }

        $second = $this->rounds->regenerate($second, $flow->talents);
        $this->assertSame(2, $second->executiveVersion->version_number);

        $first = $round->fresh();
        $this->assertSame('published', $first->status);
        $this->assertSame($hashes, [$first->executiveVersion->content_sha256, $first->technicalVersion->content_sha256, $first->executiveVersion->pdf_sha256]);
        $this->assertSame($first->id, $this->rounds->publishedRound($flow->survey)->id);
    }

    public function test_t18_technical_report_embeds_the_action_plan_and_the_annexes_without_a_third_report(): void
    {
        $flow = $this->flow();
        $measures = app(PsychosocialMeasureService::class);
        $flow->survey->forceFill(['published_at' => now()])->save();
        $measures->confirm($flow->measure, $flow->admin, null, 'Coordenação de RH', now()->addDays(20)->toDateString());
        $measures->start($flow->measure->fresh(), $flow->admin);
        $measures->addEvidence($flow->measure->fresh(), $flow->admin, 'Ata da reunião de redistribuição.');
        $flow->survey->forceFill(['published_at' => null])->save();

        $round = $this->rounds->regenerate($this->rounds->start($flow->survey->fresh(), $flow->talents), $flow->talents);
        $technical = $round->technicalVersion;
        $snapshot = $technical->snapshot;

        // Plano de ação completo, com sugerido × confirmado, status e evidência.
        $this->assertCount(1, $snapshot['plan']);
        $plan = $snapshot['plan'][0];
        $this->assertSame('Em andamento', $plan['status_label']);
        $this->assertSame('Gestão de operações', $plan['suggested_responsible']);
        $this->assertSame('Coordenação de RH', $plan['confirmed_responsible']);
        $this->assertNotNull($plan['confirmed_due_date']);
        $this->assertSame(1, $plan['evidence_count']);

        // Anexos dentro do próprio relatório: instrumento e dados agregados de suporte.
        $this->assertSame(39, $snapshot['instrument']['item_count']);
        $this->assertCount(39, $snapshot['instrument']['items']);
        $this->assertCount(39, $snapshot['support_data']['items']);

        $html = app(PsychosocialDocumentService::class)->renderHtml(PsychosocialDocument::TYPE_TECHNICAL_REPORT, $snapshot);
        foreach (['5. Plano de ação e acompanhamento', 'Revisar a distribuição das tarefas do setor.', 'Confirmado:', 'Anexo A', 'Anexo B', 'Em andamento'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringContainsString('não implementa nenhuma medida', $html);

        // O executivo é separado e não carrega os anexos; não existe terceiro documento no fluxo.
        $this->assertArrayNotHasKey('instrument', $round->executiveVersion->snapshot);
        $this->assertSame(
            [PsychosocialDocument::TYPE_EXECUTIVE_BRIEF, PsychosocialDocument::TYPE_TECHNICAL_REPORT],
            PsychosocialDocument::query()->where('survey_id', $flow->survey->id)->orderBy('type')->pluck('type')->all(),
        );
        $this->assertSame('review_round', $round->executiveVersion->snapshot['document_flow']);
    }

    public function test_legacy_documents_are_hidden_in_v11_and_kept_in_old_campaigns(): void
    {
        $flow = $this->flow();
        $documents = app(PsychosocialDocumentService::class);
        $company = $flow->company;

        $this->assertSame([], $documents->documentsSummary($flow->survey), 'V11: subsídio, dossiê e resumo antigo ficam ocultos.');

        // Liberação direta e validação pela empresa não existem na V11 (e nada é apagado nem criado).
        foreach ([
            route('admin.companies.surveys.pgr-subsidy.release', [$company->id, $flow->survey->id]),
            route('admin.companies.surveys.evidence-dossier.release', [$company->id, $flow->survey->id]),
            route('admin.companies.surveys.executive-brief.release', [$company->id, $flow->survey->id]),
        ] as $url) {
            $this->actingAs($flow->talents)->post($url)->assertRedirect()->assertSessionHas('error');
        }
        $this->assertSame(0, PsychosocialDocument::query()->count());

        $published = $this->published($flow);
        $this->actingAs($flow->admin)
            ->get(route('client.surveys.psychosocial-documents.download', [$flow->survey, 'pgr_subsidy']))->assertNotFound();
        $this->actingAs($flow->admin)
            ->get(route('client.surveys.psychosocial-documents.download', [$flow->survey, 'evidence_dossier']))->assertNotFound();
        $this->actingAs($flow->admin)
            ->get(route('client.surveys.psychosocial-dashboard', $flow->survey))
            ->assertInertia(fn (Assert $page) => $page
                ->where('psychosocialDocuments', [])
                ->where('executiveBrief', null)
                ->where('documentsUrl', route('client.surveys.documents.index', $flow->survey)));
        $this->assertNotNull($published->protocol);

        // Campanha antiga (método antigo): continua com os três documentos de sempre.
        $old = $this->createSurveyFixture();
        $summary = $documents->documentsSummary($old->survey);
        $this->assertSame(
            [PsychosocialDocument::TYPE_EXECUTIVE_BRIEF, PsychosocialDocument::TYPE_PGR_SUBSIDY, PsychosocialDocument::TYPE_EVIDENCE_DOSSIER],
            array_column($summary, 'type'),
        );
        $this->assertFalse($old->survey->usesReviewFlow());
    }

    public function test_acknowledgement_is_one_per_publication_with_user_date_and_protocol(): void
    {
        $flow = $this->flow();
        $round = $this->sent($flow);
        $ackUrl = route('client.surveys.documents.acknowledge', $flow->survey);

        // Antes de publicar: nada para a empresa.
        $this->actingAs($flow->admin)->post($ackUrl)->assertNotFound();

        $round = $this->rounds->publish($this->rounds->approve($round, $flow->reviewer), $flow->talents);

        $viewer = User::factory()->companyUser($flow->company->id)->create();
        UserPermission::query()->create([
            'user_workspace_id' => $viewer->workspaces()->firstOrFail()->id,
            'module' => PermissionModule::Relatorios->value,
            'action' => PermissionAction::View->value,
        ]);
        $recorder = User::factory()->companyUser($flow->company->id)->create();
        foreach ([PermissionAction::View, PermissionAction::Validate] as $action) {
            UserPermission::query()->create([
                'user_workspace_id' => $recorder->workspaces()->firstOrFail()->id,
                'module' => PermissionModule::Relatorios->value,
                'action' => $action->value,
            ]);
        }

        // Quem só visualiza não registra; a Talents não registra pela empresa.
        $this->actingAs($viewer)->post($ackUrl)->assertForbidden();
        $this->assertSame(0, PsychosocialReviewAcknowledgement::query()->count());
        $status = $this->actingAs($flow->talents)->post($ackUrl)->getStatusCode();
        $this->assertContains($status, [302, 403, 404]);
        $this->assertSame(0, PsychosocialReviewAcknowledgement::query()->count());

        $this->actingAs($recorder)->post($ackUrl)->assertSessionHas('success');
        $ack = PsychosocialReviewAcknowledgement::query()->sole();
        $this->assertSame($recorder->id, (int) $ack->user_id);
        $this->assertSame($round->protocol, $ack->protocol);
        $this->assertNotNull($ack->acknowledged_at);

        // Uma por publicação: a segunda é recusada, e o registro não muda.
        $this->actingAs($flow->admin)->post($ackUrl)->assertSessionHas('error');
        $this->assertSame(1, PsychosocialReviewAcknowledgement::query()->count());
        try {
            $ack->update(['protocol' => 'X']);
            $this->fail('A ciência registrada não muda.');
        } catch (LogicException) {
        }

        // Na V11 a "validação" da empresa não existe mais.
        $this->actingAs($recorder)
            ->post(route('client.surveys.psychosocial-documents.validate', [$flow->survey, 'executive_brief']), ['declaration_accepted' => '1'])
            ->assertSessionHas('error');
        $this->assertSame(0, PsychosocialDocumentVersion::query()->where('kind', PsychosocialDocumentVersion::KIND_VALIDATED)->count());

        // Nova publicação = nova ciência (a anterior fica no histórico).
        $second = $this->rounds->start($flow->survey->fresh(), $flow->talents);
        $second = $this->rounds->regenerate($second, $flow->talents);
        $second = $this->rounds->publish($this->rounds->approve($this->rounds->send($second, $flow->talents, $flow->reviewer), $flow->reviewer), $flow->talents);
        $this->actingAs($recorder)->post($ackUrl)->assertSessionHas('success');
        $this->assertSame(2, PsychosocialReviewAcknowledgement::query()->count());
        $this->assertSame($second->protocol, PsychosocialReviewAcknowledgement::query()->orderByDesc('id')->first()->protocol);
    }

    public function test_pgr_integration_is_a_separate_field_that_only_exists_with_evidence(): void
    {
        $flow = $this->flow();
        $round = $this->published($flow);
        $url = route('client.surveys.documents.pgr-integration', $flow->survey);
        $approvedBy = $round->approved_by;

        $this->actingAs($flow->admin)->post($url, ['note' => 'Sem arquivo'])->assertSessionHasErrors('evidence');
        $this->assertNull($round->fresh()->pgrIntegration);

        $this->actingAs($flow->admin)->post($url, [
            'evidence' => UploadedFile::fake()->createWithContent('pgr-sst.pdf', 'inventario de riscos'),
            'note' => 'Inventário do prestador de SST.',
        ])->assertSessionHas('success');

        $integration = $round->fresh()->pgrIntegration;
        $this->assertNotNull($integration);
        $this->assertSame(hash('sha256', 'inventario de riscos'), $integration->evidence_sha256);
        Storage::disk(PsychosocialDocumentService::DISK)->assertExists($integration->evidence_path);

        // Independente da aprovação documental: "Aprovado pela PASSEG" não muda.
        $this->assertSame($approvedBy, $round->fresh()->approved_by);
        $this->assertSame('published', $round->fresh()->status);
        $this->actingAs($flow->admin)->post($url, ['evidence' => UploadedFile::fake()->createWithContent('outro.pdf', 'x')])->assertSessionHas('error');
        $this->assertStringContainsString('revisão documental', ReviewRoundService::APPROVAL_MEANING);
    }

    public function test_profile_authorization_for_documents(): void
    {
        $flow = $this->flow();
        $company = $flow->company;
        $survey = $flow->survey;
        $round = $this->rounds->start($survey, $flow->talents);
        $adminUrls = [
            ['get', route('admin.companies.surveys.documents.index', [$company->id, $survey->id])],
            ['post', route('admin.companies.surveys.documents.start', [$company->id, $survey->id])],
            ['post', route('admin.companies.surveys.documents.regenerate', [$company->id, $survey->id, $round->id])],
            ['post', route('admin.companies.surveys.documents.send', [$company->id, $survey->id, $round->id])],
            ['post', route('admin.companies.surveys.documents.publish', [$company->id, $survey->id, $round->id])],
        ];

        // Empresa, revisor externo e Talents sem permissão não conduzem o fluxo.
        $plainAdmin = User::factory()->superAdmin()->create();
        foreach ([$flow->admin, $flow->reviewer, $plainAdmin] as $user) {
            foreach ($adminUrls as [$method, $url]) {
                $status = $this->actingAs($user)->{$method}($url)->getStatusCode();
                $this->assertContains($status, [302, 403, 404], "{$method} {$url} respondeu {$status}.");
                $this->assertNotSame(200, $status);
            }
        }
        $this->assertSame(PsychosocialReviewRound::STATUS_DRAFT, $round->fresh()->status);

        // Talents (owner): a Central abre; a campanha de outra empresa e a de método antigo são 404.
        $this->actingAs($flow->talents)->get($adminUrls[0][1])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Documents/Central')
                ->where('externalReviewEnabled', true)
                ->where('round.status_label', 'Em edição')
                ->where('canStart', false));
        $other = $this->createSurveyFixture();
        $this->actingAs($flow->talents)->get(route('admin.companies.surveys.documents.index', [$other->company->id, $survey->id]))->assertNotFound();
        $this->actingAs($flow->talents)->get(route('admin.companies.surveys.documents.index', [$other->company->id, $other->survey->id]))->assertNotFound();

        // Termina o fluxo para testar o lado da empresa: leitura exige Relatórios; outra empresa não vê.
        $round = $this->rounds->regenerate($round->fresh(), $flow->talents);
        $round = $this->rounds->send($round, $flow->talents, $flow->reviewer);
        $round = $this->rounds->publish($this->rounds->approve($round, $flow->reviewer), $flow->talents);
        $none = User::factory()->companyUser($company->id)->create();
        $this->actingAs($none)->get(route('client.surveys.documents.index', $survey))->assertForbidden();
        $outsider = User::factory()->companyAdmin($other->company->id)->create();
        $this->actingAs($outsider)->get(route('client.surveys.documents.index', $survey))->assertNotFound();
        $this->actingAs($flow->admin)->get(route('client.surveys.documents.index', $survey))->assertOk();
        $this->assertTrue($round->isPublished());
    }

    public function test_external_review_off_blocks_sending_and_the_reviewer_area(): void
    {
        $flow = $this->flow();
        $round = $this->rounds->regenerate($this->rounds->start($flow->survey, $flow->talents), $flow->talents);

        config(['nr1.external_review.enabled' => false]);

        try {
            $this->rounds->send($round, $flow->talents, $flow->reviewer);
            $this->fail('Com a revisão externa desligada não se envia à PASSEG.');
        } catch (PsychosocialDocumentException $exception) {
            $this->assertStringContainsString('desligada', $exception->getMessage());
        }
        $this->assertSame(PsychosocialReviewRound::STATUS_DRAFT, $round->fresh()->status);

        $this->actingAs($flow->reviewer)->get(route('external-review.index'))->assertNotFound();
        $this->actingAs($flow->talents)
            ->post(route('admin.companies.surveys.documents.send', [$flow->company->id, $flow->survey->id, $round->id]), ['reviewer_id' => $flow->reviewer->id])
            ->assertSessionHas('error');
    }

    public function test_admin_sends_and_publishes_through_the_routes_end_to_end(): void
    {
        $flow = $this->flow();
        $company = $flow->company->id;
        $survey = $flow->survey->id;

        $this->actingAs($flow->talents)->post(route('admin.companies.surveys.documents.start', [$company, $survey]))->assertSessionHas('success');
        $round = PsychosocialReviewRound::query()->sole();
        $this->actingAs($flow->talents)->post(route('admin.companies.surveys.documents.regenerate', [$company, $survey, $round->id]))->assertSessionHas('success');
        $this->actingAs($flow->talents)
            ->post(route('admin.companies.surveys.documents.send', [$company, $survey, $round->id]), ['reviewer_id' => $flow->reviewer->id, 'share_days' => 15])
            ->assertSessionHas('success');
        $this->assertSame('in_review', $round->fresh()->status);

        // Publicar antes de aprovar: recusado.
        $this->actingAs($flow->talents)->post(route('admin.companies.surveys.documents.publish', [$company, $survey, $round->id]))->assertSessionHas('error');

        $this->actingAs($flow->reviewer)->post(route('external-review.approve', $round->fresh()->technical_version_id))->assertSessionHas('success');
        $this->actingAs($flow->talents)->post(route('admin.companies.surveys.documents.publish', [$company, $survey, $round->id]))->assertSessionHas('success');
        $this->assertSame('published', $round->fresh()->status);

        // Download pela Talents da versão exata (com registro de acesso) e hash conferido.
        $versionId = $round->fresh()->technical_version_id;
        $this->actingAs($flow->talents)->get(route('admin.companies.surveys.documents.download', [$company, $survey, $versionId]))->assertOk();
        $this->actingAs($flow->talents)->get(route('admin.companies.surveys.documents.download', [$flow->campaign->company->id + 999, $survey, $versionId]))->assertNotFound();
    }

    public function test_old_direct_share_route_is_closed_for_v11(): void
    {
        $flow = $this->flow();
        $round = $this->rounds->regenerate($this->rounds->start($flow->survey, $flow->talents), $flow->talents);

        $this->actingAs($flow->talents)->post(
            route('admin.companies.surveys.psychosocial-documents.share', [$flow->company->id, $flow->survey->id, 'executive_brief']),
            ['reviewer_id' => $flow->reviewer->id, 'expires_at' => now()->addDays(10)->toDateString()],
        )->assertSessionHas('error');

        $this->assertSame(0, DocumentShare::query()->count());
        $this->assertNotNull($round->executive_version_id);
    }
}
