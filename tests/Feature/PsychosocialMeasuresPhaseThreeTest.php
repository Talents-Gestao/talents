<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\WipeCompanyDataCommand;
use App\Exceptions\PsychosocialMeasureException;
use App\Models\ActionPlan;
use App\Models\ActionPlanItem;
use App\Models\PsychosocialMeasure;
use App\Models\PsychosocialMeasureEvent;
use App\Models\User;
use App\Services\PsychosocialMeasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\CreatesSurveyFixtures;
use Tests\TestCase;

class PsychosocialMeasuresPhaseThreeTest extends TestCase
{
    use CreatesSurveyFixtures;
    use RefreshDatabase;

    private function service(): PsychosocialMeasureService
    {
        return app(PsychosocialMeasureService::class);
    }

    /**
     * @return array<string, string>
     */
    private function suggestionData(): array
    {
        return [
            'factor_label' => 'Exigências elevadas e ritmo de trabalho',
            'measure_description' => 'Revisar volume, ritmo, metas e capacidade de trabalho.',
        ];
    }

    public function test_engine_suggestion_is_born_to_approve_as_questionnaire_indication(): void
    {
        $fx = $this->createSurveyFixture();

        $measure = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData());

        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $measure->status);
        $this->assertSame('A aprovar', $measure->statusLabel());
        $this->assertSame(PsychosocialMeasure::ORIGIN_ENGINE_SUGGESTION, $measure->origin);
        $this->assertSame(PsychosocialMeasure::BASIS_QUESTIONNAIRE_INDICATION, $measure->evidence_basis);
        $this->assertSame($fx->company->id, $measure->company_id);
        $this->assertNull($measure->responsible_text);
        $this->assertNull($measure->sst_priority);

        $event = $measure->events()->sole();
        $this->assertSame(PsychosocialMeasureEvent::FIELD_CREATED, $event->field);
        $this->assertNull($event->user_id);
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $event->new_value);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function engineForbiddenInputs(): array
    {
        return [
            'responsável' => [['responsible_text' => 'RH']],
            'prioridade SST' => [['sst_priority' => 'alta']],
            'status implantada' => [['status' => PsychosocialMeasure::STATUS_IMPLEMENTED]],
            'status pendente' => [['status' => PsychosocialMeasure::STATUS_PENDING]],
            'fator validado' => [['evidence_basis' => PsychosocialMeasure::BASIS_VALIDATED_FACTOR]],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('engineForbiddenInputs')]
    public function test_engine_suggestion_cannot_fill_human_fields(array $extra): void
    {
        $fx = $this->createSurveyFixture();

        try {
            $this->service()->createEngineSuggestion($fx->survey, array_merge($this->suggestionData(), $extra));
            $this->fail('A sugestão do motor deveria ter sido rejeitada.');
        } catch (PsychosocialMeasureException) {
            $this->assertSame(0, PsychosocialMeasure::query()->count());
        }
    }

    public function test_manual_measure_rejects_unknown_status_including_legacy_done(): void
    {
        $fx = $this->createSurveyFixture();
        $actor = User::factory()->create();

        $this->expectException(PsychosocialMeasureException::class);
        $this->service()->createManual($fx->survey, array_merge($this->suggestionData(), [
            'evidence_basis' => PsychosocialMeasure::BASIS_VALIDATED_FACTOR,
            'status' => 'done',
        ]), $actor);
    }

    public function test_implemented_requires_a_user_and_records_who_and_when(): void
    {
        $fx = $this->createSurveyFixture();
        $actor = User::factory()->create();
        $measure = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData());

        try {
            $this->service()->changeStatus($measure, PsychosocialMeasure::STATUS_IMPLEMENTED, null);
            $this->fail('«Implantada» sem usuário deveria ser rejeitada.');
        } catch (PsychosocialMeasureException) {
            $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $measure->fresh()->status);
        }

        $this->service()->changeStatus($measure, PsychosocialMeasure::STATUS_IMPLEMENTED, $actor, 'Rotina implantada');
        $measure->refresh();

        $this->assertSame(PsychosocialMeasure::STATUS_IMPLEMENTED, $measure->status);
        $this->assertSame($actor->id, $measure->implemented_by);
        $this->assertNotNull($measure->implemented_at);

        $event = $measure->events()->where('field', 'status')->sole();
        $this->assertSame($actor->id, $event->user_id);
        $this->assertSame(PsychosocialMeasure::STATUS_TO_APPROVE, $event->old_value);
        $this->assertSame(PsychosocialMeasure::STATUS_IMPLEMENTED, $event->new_value);
        $this->assertSame('Rotina implantada', $event->reason);

        $this->service()->changeStatus($measure, PsychosocialMeasure::STATUS_IN_PROGRESS, $actor);
        $measure->refresh();
        $this->assertNull($measure->implemented_at);
        $this->assertNull($measure->implemented_by);
    }

    public function test_model_rejects_implemented_without_user_and_unknown_status_outside_service(): void
    {
        $fx = $this->createSurveyFixture();
        $measure = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData());

        try {
            $measure->update(['status' => PsychosocialMeasure::STATUS_IMPLEMENTED, 'implemented_at' => now()]);
            $this->fail('Implantada sem implemented_by deveria ser rejeitada.');
        } catch (PsychosocialMeasureException) {
        }

        $this->expectException(PsychosocialMeasureException::class);
        $measure->fresh()->update(['status' => 'in_progress_x']);
    }

    public function test_update_records_one_event_per_changed_field_and_blocks_fixed_fields(): void
    {
        $fx = $this->createSurveyFixture();
        $actor = User::factory()->create();
        $measure = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData());

        $this->service()->update($measure, [
            'responsible_text' => 'RH + lideranças',
            'sst_priority' => 'alta',
            'factor_label' => $measure->factor_label,
        ], $actor, 'Aprovação em reunião');

        $fields = $measure->events()->where('field', '!=', PsychosocialMeasureEvent::FIELD_CREATED)->pluck('field')->all();
        $this->assertEqualsCanonicalizing(['responsible_text', 'sst_priority'], $fields);

        foreach (['status', 'origin', 'survey_id', 'implemented_by'] as $fixedField) {
            try {
                $this->service()->update($measure, [$fixedField => 'x'], $actor);
                $this->fail("O campo {$fixedField} não deveria ser editável.");
            } catch (PsychosocialMeasureException) {
            }
        }
    }

    public function test_events_are_append_only(): void
    {
        $fx = $this->createSurveyFixture();
        $event = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData())->events()->sole();

        try {
            $event->update(['reason' => 'alterado']);
            $this->fail('Evento não deveria aceitar update.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_service_never_touches_action_plan_items(): void
    {
        $fx = $this->createSurveyFixture();
        $actor = User::factory()->create();
        $plan = ActionPlan::query()->create(['company_id' => $fx->company->id, 'survey_id' => $fx->survey->id, 'status' => 'open']);
        $item = ActionPlanItem::query()->create(['action_plan_id' => $plan->id, 'title' => 'Item antigo', 'status' => 'pending', 'sort_order' => 0]);

        $measure = $this->service()->createEngineSuggestion($fx->survey, $this->suggestionData());
        $this->service()->changeStatus($measure, PsychosocialMeasure::STATUS_IMPLEMENTED, $actor);

        $this->assertSame(1, ActionPlanItem::query()->count());
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_legacy_action_plan_item_patch_still_accepts_pending_in_progress_and_done(): void
    {
        $fx = $this->createSurveyFixture();
        $plan = ActionPlan::query()->create(['company_id' => $fx->company->id, 'survey_id' => $fx->survey->id, 'status' => 'open']);
        $item = ActionPlanItem::query()->create(['action_plan_id' => $plan->id, 'title' => 'Item antigo', 'status' => 'pending', 'sort_order' => 0]);
        $user = User::factory()->companyAdmin($fx->company->id)->create();

        foreach (['in_progress', 'done', 'pending'] as $status) {
            $this->actingAs($user)
                ->patch(route('client.action-plan-items.update', $item), ['status' => $status])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame($status, $item->fresh()->status);
        }

        $this->actingAs($user)
            ->patch(route('client.action-plan-items.update', $item), ['status' => PsychosocialMeasure::STATUS_TO_APPROVE])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, PsychosocialMeasure::query()->count());
    }

    public function test_wipe_removes_company_events_and_measures_before_surveys(): void
    {
        $target = $this->createSurveyFixture();
        $other = $this->createSurveyFixture();
        $actor = User::factory()->create();

        $targetMeasure = $this->service()->createEngineSuggestion($target->survey, $this->suggestionData());
        $this->service()->changeStatus($targetMeasure, PsychosocialMeasure::STATUS_PENDING, $actor);
        $otherMeasure = $this->service()->createEngineSuggestion($other->survey, $this->suggestionData());

        $wipe = new ReflectionMethod(WipeCompanyDataCommand::class, 'wipe');
        $wipe->invoke(app(WipeCompanyDataCommand::class), $target->company->id);

        $this->assertSame(0, PsychosocialMeasure::query()->where('company_id', $target->company->id)->count());
        $this->assertSame(0, PsychosocialMeasureEvent::query()->where('measure_id', $targetMeasure->id)->count());
        $this->assertNotNull($otherMeasure->fresh());
        $this->assertSame(1, $otherMeasure->events()->count());
    }
}
