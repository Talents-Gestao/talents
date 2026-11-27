<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\TalentCandidateAuditAction;
use App\Models\TalentCandidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de candidatos (Contratação · Candidatos). Excluir = arquivar; não há exclusão definitiva.
 * Arquivar e restaurar não mexem em candidaturas, entrevistas nem anexos.
 */
final class TalentCandidateService
{
    /** Campos editáveis pela ficha. */
    public const EDITABLE = [
        'name',
        'email',
        'phone',
        'email_reviewed',
        'phone_reviewed',
        'email_opt_in',
        'contact_preference',
        'source',
        'current_role',
        'current_company',
        'notes',
    ];

    /** Campos técnicos que não aparecem na auditoria de alteração. */
    private const NOT_AUDITED = ['email_normalized', 'phone_digits', 'updated_by', 'updated_at', 'created_at'];

    public function __construct(
        private readonly TalentCandidateResolver $resolver,
        private readonly TalentCandidateAuditor $auditor,
    ) {}

    /**
     * Cria pela ficha. Se o e-mail ou o solides_id já existir, recusa e aponta o cadastro existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): TalentCandidate
    {
        return DB::transaction(function () use ($data, $actor): TalentCandidate {
            $existing = $this->resolver->findExisting($data['solides_id'] ?? null, $data['email'] ?? null);
            if ($existing !== null) {
                throw ValidationException::withMessages(
                    $this->resolver->existingMessages($existing, $actor),
                );
            }

            $candidate = TalentCandidate::query()->create([
                ...$this->only($data),
                'solides_id' => $data['solides_id'] ?? null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->auditor->record($candidate, TalentCandidateAuditAction::Created, $actor?->id);

            return $candidate;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TalentCandidate $candidate, array $data, ?User $actor): TalentCandidate
    {
        return DB::transaction(function () use ($candidate, $data, $actor): TalentCandidate {
            $candidate->fill($this->only($data));

            if ($candidate->isDirty('email')) {
                $email = TalentCandidate::normalizeEmail($candidate->email);
                if ($email !== null) {
                    $other = TalentCandidate::query()
                        ->where('email_normalized', $email)
                        ->whereKeyNot($candidate->id)
                        ->first();
                    if ($other !== null) {
                        throw ValidationException::withMessages(
                            $this->resolver->existingMessages($other, $actor),
                        );
                    }
                }
            }

            $candidate->updated_by = $actor?->id;
            $candidate->save();

            $changed = array_values(array_diff(array_keys($candidate->getChanges()), self::NOT_AUDITED));
            if ($changed !== []) {
                $this->auditor->record($candidate, TalentCandidateAuditAction::Updated, $actor?->id, [
                    'fields' => $changed,
                ]);
            }

            return $candidate;
        });
    }

    public function archive(TalentCandidate $candidate, ?User $actor): TalentCandidate
    {
        if ($candidate->isArchived()) {
            return $candidate;
        }

        return DB::transaction(function () use ($candidate, $actor): TalentCandidate {
            $candidate->archived_at = now();
            $candidate->archived_by = $actor?->id;
            $candidate->updated_by = $actor?->id;
            $candidate->save();

            $this->auditor->record($candidate, TalentCandidateAuditAction::Archived, $actor?->id);

            return $candidate;
        });
    }

    public function restore(TalentCandidate $candidate, ?User $actor): TalentCandidate
    {
        if (! $candidate->isArchived()) {
            return $candidate;
        }

        return DB::transaction(function () use ($candidate, $actor): TalentCandidate {
            $email = $candidate->email_normalized;
            if ($email !== null) {
                $active = TalentCandidate::query()
                    ->active()
                    ->where('email_normalized', $email)
                    ->whereKeyNot($candidate->id)
                    ->first();
                if ($active !== null) {
                    throw ValidationException::withMessages([
                        'candidate' => 'Já existe outro cadastro ativo com este e-mail. Não é possível restaurar sem duplicar.',
                    ]);
                }
            }

            $candidate->archived_at = null;
            $candidate->archived_by = null;
            $candidate->updated_by = $actor?->id;
            $candidate->save();

            $this->auditor->record($candidate, TalentCandidateAuditAction::Restored, $actor?->id);

            return $candidate;
        });
    }

    /**
     * «Cadastrar no Talents» a partir do Banco de talentos (Sólides).
     * Reaproveita por solides_id e depois por e-mail; nunca sobrescreve dados já existentes.
     *
     * @param  array{solides_id: string, name?: string|null, email?: string|null, phone?: string|null}  $data
     * @return array{candidate: TalentCandidate, created: bool}
     */
    public function importFromSolides(array $data, ?User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $solidesId = trim((string) $data['solides_id']);
            $existing = $this->resolver->findExisting($solidesId, $data['email'] ?? null);

            if ($existing !== null) {
                if ($existing->solides_id === null) {
                    $existing->solides_id = $solidesId;
                    $existing->updated_by = $actor?->id;
                    $existing->save();
                    $this->auditor->record($existing, TalentCandidateAuditAction::ImportedSolides, $actor?->id, [
                        'solides_id' => $solidesId,
                        'linked_existing' => true,
                    ]);
                }

                return ['candidate' => $existing, 'created' => false];
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => 'O currículo da Sólides não tem nome; cadastre o candidato manualmente.',
                ]);
            }

            $candidate = TalentCandidate::query()->create([
                'solides_id' => $solidesId,
                'name' => $name,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'source' => 'Sólides',
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->auditor->record($candidate, TalentCandidateAuditAction::ImportedSolides, $actor?->id, [
                'solides_id' => $solidesId,
            ]);

            return ['candidate' => $candidate, 'created' => true];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function only(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EDITABLE));
    }
}
