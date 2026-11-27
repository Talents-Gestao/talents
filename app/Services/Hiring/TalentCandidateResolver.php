<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\AdminPermissionModule;
use App\Enums\PermissionAction;
use App\Enums\TalentCandidateAuditAction;
use App\Models\TalentCandidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encontra o cadastro do candidato ou cria um novo.
 * Identifica a pessoa por solides_id e depois por e-mail normalizado; o nome nunca identifica.
 * Cadastro arquivado não é reaproveitado nem duplicado: gera erro pedindo para restaurar,
 * com o link da ficha (erro «existing_candidate_url») para quem pode restaurar.
 */
final class TalentCandidateResolver
{
    public const ARCHIVED_MESSAGE = 'Já existe um cadastro arquivado para este candidato. Restaure o cadastro antes de incluí-lo numa vaga.';

    public const EXISTING_MESSAGE = 'Já existe um cadastro com este e-mail ou ID da Sólides.';

    public function __construct(
        private readonly TalentCandidateAuditor $auditor,
    ) {}

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null, solides_id?: string|null, source?: string|null, current_role?: string|null, current_company?: string|null}  $data
     */
    public function findOrCreate(array $data, ?int $userId = null, ?User $actor = null): TalentCandidate
    {
        return DB::transaction(function () use ($data, $userId, $actor): TalentCandidate {
            $solidesId = $this->clean($data['solides_id'] ?? null);
            $email = TalentCandidate::normalizeEmail($data['email'] ?? null);

            $existing = $this->findExisting($solidesId, $email, lock: true);
            if ($existing !== null) {
                if ($existing->isArchived()) {
                    $this->failArchived($existing, $actor);
                }
                if ($solidesId !== null && $existing->solides_id === null) {
                    $existing->solides_id = $solidesId;
                    $existing->updated_by = $userId;
                    $existing->save();
                }

                return $existing;
            }

            $name = $this->clean($data['name'] ?? null);
            if ($name === null) {
                throw ValidationException::withMessages([
                    'name' => 'Informe o nome do candidato.',
                ]);
            }

            $candidate = TalentCandidate::query()->create([
                'solides_id' => $solidesId,
                'name' => $name,
                'email' => $email,
                'phone' => $this->clean($data['phone'] ?? null),
                'source' => $this->clean($data['source'] ?? null),
                'current_role' => $this->clean($data['current_role'] ?? null),
                'current_company' => $this->clean($data['current_company'] ?? null),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditor->record($candidate, TalentCandidateAuditAction::Created, $userId);

            return $candidate;
        });
    }

    /**
     * Cadastro da mesma pessoa: por solides_id e depois por e-mail normalizado
     * (o ativo tem prioridade sobre o arquivado). Nunca pelo nome.
     */
    public function findExisting(?string $solidesId, ?string $email, bool $lock = false): ?TalentCandidate
    {
        $solidesId = $this->clean($solidesId);
        $email = TalentCandidate::normalizeEmail($email);

        if ($solidesId !== null) {
            $query = TalentCandidate::query()->where('solides_id', $solidesId);
            $found = ($lock ? $query->lockForUpdate() : $query)->first();
            if ($found !== null) {
                return $found;
            }
        }

        if ($email !== null) {
            $query = TalentCandidate::query()
                ->where('email_normalized', $email)
                ->orderByRaw('case when archived_at is null then 0 else 1 end')
                ->orderBy('id');

            return ($lock ? $query->lockForUpdate() : $query)->first();
        }

        return null;
    }

    /**
     * Mensagens de erro apontando o cadastro existente. O link só vai para quem pode abrir
     * a ficha (e, se arquivado, restaurar).
     *
     * @return array<string, string>
     */
    public function existingMessages(TalentCandidate $existing, ?User $actor): array
    {
        $messages = [
            'email' => $existing->isArchived() ? self::ARCHIVED_MESSAGE : self::EXISTING_MESSAGE,
        ];

        $action = $existing->isArchived() ? PermissionAction::Delete : PermissionAction::View;
        if ($actor !== null
            && $actor->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, PermissionAction::View)
            && $actor->canAccessAdmin(AdminPermissionModule::ContratacaoCandidatos, $action)) {
            $messages['existing_candidate_url'] = route('admin.contratacao.candidatos.show', $existing);
        }

        return $messages;
    }

    private function failArchived(TalentCandidate $candidate, ?User $actor): never
    {
        throw ValidationException::withMessages($this->existingMessages($candidate, $actor));
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
