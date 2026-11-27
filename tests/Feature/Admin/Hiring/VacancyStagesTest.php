<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Hiring;

use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Models\TalentCandidate;
use App\Services\Hiring\HiringVacancyService;
use Illuminate\Validation\ValidationException;

class VacancyStagesTest extends HiringVacancyTestCase
{
    public function test_new_stage_goes_right_before_hired_stage_and_name_is_unique(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.store', $vacancy), ['name' => 'Teste prático'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Triagem', 'Entrevista', 'Finalistas', 'Teste prático', 'Contratado'],
            $vacancy->stages()->pluck('name')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5], $vacancy->stages()->pluck('position')->all());

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.store', $vacancy), ['name' => ' triagem '])
            ->assertSessionHasErrors('name');

        $entrevista = $vacancy->stages()->where('name', 'Entrevista')->firstOrFail();
        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.stages.update', [$vacancy, $entrevista]), ['name' => 'FINALISTAS'])
            ->assertSessionHasErrors('name');
        $this->actingAs($owner)
            ->patch(route('admin.contratacao.vagas.stages.update', [$vacancy, $entrevista]), ['name' => 'Entrevista técnica'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Entrevista técnica', $entrevista->fresh()->name);
    }

    public function test_delete_stage_rules(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        [$triagem, $entrevista, , $contratado] = $vacancy->stages->all();

        app(HiringVacancyService::class)->addCandidate($vacancy, TalentCandidate::query()->create(['name' => 'Ivo']), $triagem, null);

        // Com candidatos: recusada.
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.stages.destroy', [$vacancy, $triagem]))
            ->assertSessionHasErrors('stage');
        // Etapa de contratado: recusada.
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.stages.destroy', [$vacancy, $contratado]))
            ->assertSessionHasErrors('stage');
        // Vazia e comum: excluída, posições renumeradas.
        $this->actingAs($owner)
            ->delete(route('admin.contratacao.vagas.stages.destroy', [$vacancy, $entrevista]))
            ->assertSessionHasNoErrors();

        $this->assertNull($entrevista->fresh());
        $this->assertSame(['Triagem', 'Finalistas', 'Contratado'], $vacancy->stages()->pluck('name')->all());
        $this->assertSame([1, 2, 3], $vacancy->stages()->pluck('position')->all());
    }

    public function test_only_stage_cannot_be_deleted(): void
    {
        $process = $this->process();
        $vacancy = HiringVacancy::query()->create([
            'hiring_process_id' => $process->id,
            'company_id' => $process->company_id,
            'title' => 'Única etapa',
        ]);
        $only = $vacancy->stages()->create(['name' => 'Triagem', 'position' => 1, 'is_hired' => false]);

        $this->expectException(ValidationException::class);
        app(HiringVacancyService::class)->deleteStage($only, null);
    }

    public function test_reorder_requires_complete_list(): void
    {
        $owner = $this->owner();
        $vacancy = $this->vacancy();
        $ids = $vacancy->stages->pluck('id')->all();

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.reorder', $vacancy), ['ordered_ids' => array_slice($ids, 0, 3)])
            ->assertSessionHasErrors('ordered_ids');

        $otherStage = $this->vacancy(null, 'Outra')->stages()->first();
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.reorder', $vacancy), [
                'ordered_ids' => [...array_slice($ids, 0, 3), $otherStage->id],
            ])
            ->assertSessionHasErrors('ordered_ids');

        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.reorder', $vacancy), ['ordered_ids' => [$ids[0], $ids[0], $ids[1], $ids[2]]])
            ->assertSessionHasErrors('ordered_ids');

        $reversed = array_reverse($ids);
        $this->actingAs($owner)
            ->post(route('admin.contratacao.vagas.stages.reorder', $vacancy), ['ordered_ids' => $reversed])
            ->assertSessionHasNoErrors();

        $this->assertSame($reversed, $vacancy->stages()->pluck('id')->all());
        $this->assertSame(1, HiringVacancyStage::query()->whereKey($ids[3])->value('position'));
    }
}
