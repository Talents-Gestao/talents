<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\HiringApplicationEventType;
use App\Enums\HiringProcessStage;
use App\Enums\HiringVacancyStatus;
use App\Models\HiringApplication;
use App\Models\HiringApplicationEvent;
use App\Models\HiringProcess;
use App\Models\HiringVacancy;
use App\Models\HiringVacancyStage;
use App\Models\TalentCandidate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regras das vagas de contratação: etapas próprias por vaga, candidaturas e histórico.
 * Tudo em transação. Nesta fase nenhuma ação envia aviso ou e-mail.
 */
final class HiringVacancyService
{
    /** Etapas padrão de toda vaga nova; a última é a de contratado. */
    public const DEFAULT_STAGES = ['Triagem', 'Entrevista', 'Finalistas', 'Contratado'];

    public const MAX_STAGES = 20;

    /**
     * @param  array{title: string, description?: string|null}  $data
     */
    public function create(HiringProcess $process, array $data, ?int $userId): HiringVacancy
    {
        if ($process->current_stage === HiringProcessStage::Contratacao) {
            throw ValidationException::withMessages([
                'hiring_process_id' => 'Escolha um processo que ainda não esteja na fase Contratação.',
            ]);
        }

        return DB::transaction(function () use ($process, $data, $userId): HiringVacancy {
            $vacancy = HiringVacancy::query()->create([
                'hiring_process_id' => $process->id,
                'company_id' => $process->company_id,
                'title' => trim($data['title']),
                'description' => $this->cleanText($data['description'] ?? null),
                'status' => HiringVacancyStatus::Open,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $last = count(self::DEFAULT_STAGES) - 1;
            foreach (self::DEFAULT_STAGES as $index => $name) {
                $vacancy->stages()->create([
                    'name' => $name,
                    'position' => $index + 1,
                    'is_hired' => $index === $last,
                ]);
            }

            return $vacancy;
        });
    }

    /**
     * @param  array{title?: string, description?: string|null, status?: HiringVacancyStatus|string}  $data
     */
    public function update(HiringVacancy $vacancy, array $data, ?int $userId): HiringVacancy
    {
        return DB::transaction(function () use ($vacancy, $data, $userId): HiringVacancy {
            if (array_key_exists('title', $data)) {
                $vacancy->title = trim((string) $data['title']);
            }
            if (array_key_exists('description', $data)) {
                $vacancy->description = $this->cleanText($data['description']);
            }
            if (array_key_exists('status', $data)) {
                $status = $data['status'] instanceof HiringVacancyStatus
                    ? $data['status']
                    : HiringVacancyStatus::from((string) $data['status']);

                if ($status !== $vacancy->status) {
                    $vacancy->status = $status;
                    $vacancy->closed_at = $status->isClosed() ? now() : null;
                }
            }

            $vacancy->updated_by = $userId;
            $vacancy->save();

            return $vacancy;
        });
    }

    /** Nova etapa entra logo antes da etapa de contratado. */
    public function addStage(HiringVacancy $vacancy, string $name, ?int $userId): HiringVacancyStage
    {
        return DB::transaction(function () use ($vacancy, $name, $userId): HiringVacancyStage {
            $stages = $this->lockedStages($vacancy);

            if ($stages->count() >= self::MAX_STAGES) {
                throw ValidationException::withMessages([
                    'name' => 'A vaga já tem o máximo de '.self::MAX_STAGES.' etapas.',
                ]);
            }

            $name = $this->assertUniqueName($stages, $name);

            $hired = $stages->firstWhere('is_hired', true);
            $position = $hired !== null ? $hired->position : ((int) $stages->max('position')) + 1;

            foreach ($stages as $stage) {
                if ($stage->position >= $position) {
                    $stage->position = $stage->position + 1;
                    $stage->save();
                }
            }

            $stage = $vacancy->stages()->create([
                'name' => $name,
                'position' => $position,
                'is_hired' => false,
            ]);

            $this->touchVacancy($vacancy, $userId);

            return $stage;
        });
    }

    public function renameStage(HiringVacancyStage $stage, string $name, ?int $userId): HiringVacancyStage
    {
        return DB::transaction(function () use ($stage, $name, $userId): HiringVacancyStage {
            $vacancy = $stage->vacancy;
            $stages = $this->lockedStages($vacancy);

            $stage->name = $this->assertUniqueName($stages, $name, $stage->id);
            $stage->save();

            $this->touchVacancy($vacancy, $userId);

            return $stage;
        });
    }

    /** Só uma etapa é a de contratado; hired_at das candidaturas é recalculado. */
    public function markHiredStage(HiringVacancyStage $stage, ?int $userId): HiringVacancyStage
    {
        return DB::transaction(function () use ($stage, $userId): HiringVacancyStage {
            $vacancy = $stage->vacancy;
            $this->lockedStages($vacancy);

            HiringVacancyStage::query()
                ->where('hiring_vacancy_id', $vacancy->id)
                ->whereKeyNot($stage->id)
                ->update(['is_hired' => false]);

            $stage->is_hired = true;
            $stage->save();

            HiringApplication::query()
                ->where('hiring_vacancy_id', $vacancy->id)
                ->where('hiring_vacancy_stage_id', '!=', $stage->id)
                ->whereNotNull('hired_at')
                ->update(['hired_at' => null]);

            HiringApplication::query()
                ->where('hiring_vacancy_id', $vacancy->id)
                ->where('hiring_vacancy_stage_id', $stage->id)
                ->whereNull('hired_at')
                ->update(['hired_at' => now()]);

            $this->touchVacancy($vacancy, $userId);

            return $stage;
        });
    }

    /** Excluir etapa: só sem candidatos, nunca a única nem a de contratado. */
    public function deleteStage(HiringVacancyStage $stage, ?int $userId): void
    {
        DB::transaction(function () use ($stage, $userId): void {
            $vacancy = $stage->vacancy;
            $stages = $this->lockedStages($vacancy);

            if ($stages->count() <= 1) {
                throw ValidationException::withMessages([
                    'stage' => 'A vaga precisa ter pelo menos uma etapa.',
                ]);
            }

            if ($stage->is_hired) {
                throw ValidationException::withMessages([
                    'stage' => 'A etapa de contratado não pode ser excluída. Marque outra etapa como contratado antes.',
                ]);
            }

            if ($stage->applications()->exists()) {
                throw ValidationException::withMessages([
                    'stage' => 'Mova os candidatos desta etapa antes de excluí-la.',
                ]);
            }

            $stage->delete();

            $this->renumberStages($vacancy);
            $this->touchVacancy($vacancy, $userId);
        });
    }

    /**
     * @param  list<int>  $orderedIds  Lista completa das etapas da vaga, na nova ordem.
     */
    public function reorderStages(HiringVacancy $vacancy, array $orderedIds, ?int $userId): void
    {
        DB::transaction(function () use ($vacancy, $orderedIds, $userId): void {
            $stages = $this->lockedStages($vacancy);

            $ids = array_map('intval', $orderedIds);
            $current = $stages->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = $ids;
            sort($given);

            if (count($ids) !== count(array_unique($ids)) || $given !== $current) {
                throw ValidationException::withMessages([
                    'ordered_ids' => 'Envie a lista completa das etapas desta vaga.',
                ]);
            }

            $byId = $stages->keyBy('id');
            foreach ($ids as $index => $id) {
                $stage = $byId[$id];
                if ($stage->position !== $index + 1) {
                    $stage->position = $index + 1;
                    $stage->save();
                }
            }

            $this->touchVacancy($vacancy, $userId);
        });
    }

    /** Candidato entra uma vez por vaga e nunca arquivado. */
    public function addCandidate(
        HiringVacancy $vacancy,
        TalentCandidate $candidate,
        ?HiringVacancyStage $stage,
        ?int $userId,
    ): HiringApplication {
        if ($candidate->isArchived()) {
            throw ValidationException::withMessages([
                'talent_candidate_id' => TalentCandidateResolver::ARCHIVED_MESSAGE,
            ]);
        }

        if ($stage !== null && (int) $stage->hiring_vacancy_id !== (int) $vacancy->id) {
            throw ValidationException::withMessages([
                'stage_id' => 'Etapa inválida para esta vaga.',
            ]);
        }

        try {
            return DB::transaction(function () use ($vacancy, $candidate, $stage, $userId): HiringApplication {
                $stages = $this->lockedStages($vacancy);
                $stage ??= $stages->first();

                if ($stage === null) {
                    throw ValidationException::withMessages([
                        'stage_id' => 'A vaga não tem etapas.',
                    ]);
                }

                $exists = HiringApplication::query()
                    ->where('hiring_vacancy_id', $vacancy->id)
                    ->where('talent_candidate_id', $candidate->id)
                    ->exists();

                if ($exists) {
                    $this->failDuplicate();
                }

                $sortOrder = ((int) HiringApplication::query()
                    ->where('hiring_vacancy_stage_id', $stage->id)
                    ->max('sort_order')) + 1;

                $application = HiringApplication::query()->create([
                    'hiring_vacancy_id' => $vacancy->id,
                    'talent_candidate_id' => $candidate->id,
                    'hiring_vacancy_stage_id' => $stage->id,
                    'sort_order' => $sortOrder,
                    'hired_at' => $stage->is_hired ? now() : null,
                    'created_by' => $userId,
                ]);

                $this->recordEvent($application, HiringApplicationEventType::Added, null, $stage->id, null, $userId);
                $this->touchVacancy($vacancy, $userId);

                return $application;
            });
        } catch (UniqueConstraintViolationException) {
            $this->failDuplicate();
        }
    }

    /**
     * Move a candidatura para a etapa (da mesma vaga) na posição indicada (0 = topo).
     * Registra evento «moved» quando a etapa muda; hired_at segue a etapa.
     */
    public function moveApplication(
        HiringApplication $application,
        HiringVacancyStage $target,
        ?int $position,
        ?int $userId,
    ): HiringApplication {
        if ((int) $target->hiring_vacancy_id !== (int) $application->hiring_vacancy_id) {
            throw ValidationException::withMessages([
                'stage_id' => 'Etapa inválida para esta vaga.',
            ]);
        }

        return DB::transaction(function () use ($application, $target, $position, $userId): HiringApplication {
            $vacancy = $application->vacancy;
            $this->lockedStages($vacancy);

            $fromStageId = (int) $application->hiring_vacancy_stage_id;
            $stageChanged = $fromStageId !== (int) $target->id;

            $siblings = HiringApplication::query()
                ->where('hiring_vacancy_stage_id', $target->id)
                ->whereKeyNot($application->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $index = $position === null ? $siblings->count() : max(0, min($position, $siblings->count()));
            $ordered = $siblings->all();
            array_splice($ordered, $index, 0, [$application]);

            $application->hiring_vacancy_stage_id = $target->id;
            if ($target->is_hired) {
                $application->hired_at ??= now();
            } else {
                $application->hired_at = null;
            }

            foreach ($ordered as $i => $item) {
                $item->sort_order = $i + 1;
                $item->save();
            }

            if ($stageChanged) {
                $this->renumberApplications($fromStageId);
                $this->recordEvent($application, HiringApplicationEventType::Moved, $fromStageId, (int) $target->id, null, $userId);
            }

            $this->touchVacancy($vacancy, $userId);

            return $application;
        });
    }

    public function addNote(HiringApplication $application, string $note, ?int $userId): HiringApplicationEvent
    {
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'Escreva a anotação.',
            ]);
        }

        return DB::transaction(function () use ($application, $note, $userId): HiringApplicationEvent {
            $stageId = (int) $application->hiring_vacancy_stage_id;

            return $this->recordEvent($application, HiringApplicationEventType::Note, $stageId, $stageId, $note, $userId);
        });
    }

    /** Retira o candidato da vaga; o cadastro do candidato continua. */
    public function removeApplication(HiringApplication $application, ?int $userId): void
    {
        if ($application->assessment()->whereNotNull('approved_at')->exists()) {
            throw ValidationException::withMessages([
                'talent_candidate_id' => 'Esta candidatura tem dossiê aprovado e não pode ser retirada da vaga.',
            ]);
        }

        DB::transaction(function () use ($application, $userId): void {
            $vacancy = $application->vacancy;
            $stageId = (int) $application->hiring_vacancy_stage_id;

            $application->delete();

            $this->renumberApplications($stageId);
            $this->touchVacancy($vacancy, $userId);
        });
    }

    /**
     * @return Collection<int, HiringVacancyStage>
     */
    private function lockedStages(HiringVacancy $vacancy)
    {
        HiringVacancy::query()->whereKey($vacancy->id)->lockForUpdate()->first();

        return HiringVacancyStage::query()
            ->where('hiring_vacancy_id', $vacancy->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  Collection<int, HiringVacancyStage>  $stages
     */
    private function assertUniqueName($stages, string $name, ?int $ignoreId = null): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Informe o nome da etapa.',
            ]);
        }

        if (mb_strlen($name) > 80) {
            throw ValidationException::withMessages([
                'name' => 'O nome da etapa pode ter até 80 caracteres.',
            ]);
        }

        $key = mb_strtolower($name);
        foreach ($stages as $stage) {
            if ($ignoreId !== null && (int) $stage->id === $ignoreId) {
                continue;
            }
            if (mb_strtolower($stage->name) === $key) {
                throw ValidationException::withMessages([
                    'name' => 'Já existe uma etapa com este nome nesta vaga.',
                ]);
            }
        }

        return $name;
    }

    private function renumberStages(HiringVacancy $vacancy): void
    {
        $stages = HiringVacancyStage::query()
            ->where('hiring_vacancy_id', $vacancy->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($stages->values() as $index => $stage) {
            if ($stage->position !== $index + 1) {
                $stage->position = $index + 1;
                $stage->save();
            }
        }
    }

    private function renumberApplications(int $stageId): void
    {
        $applications = HiringApplication::query()
            ->where('hiring_vacancy_stage_id', $stageId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($applications->values() as $index => $application) {
            if ($application->sort_order !== $index + 1) {
                $application->sort_order = $index + 1;
                $application->save();
            }
        }
    }

    private function recordEvent(
        HiringApplication $application,
        HiringApplicationEventType $type,
        ?int $fromStageId,
        ?int $toStageId,
        ?string $note,
        ?int $userId,
    ): HiringApplicationEvent {
        return HiringApplicationEvent::query()->create([
            'hiring_application_id' => $application->id,
            'type' => $type,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'note' => $note,
            'user_id' => $userId,
        ]);
    }

    private function touchVacancy(HiringVacancy $vacancy, ?int $userId): void
    {
        HiringVacancy::query()->whereKey($vacancy->id)->update([
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }

    private function failDuplicate(): never
    {
        throw ValidationException::withMessages([
            'talent_candidate_id' => 'Este candidato já está nesta vaga.',
        ]);
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
