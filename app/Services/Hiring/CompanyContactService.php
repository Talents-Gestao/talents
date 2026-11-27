<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\CompanyContactEventType;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\CompanyContactEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contatos da empresa: um principal por empresa, conferência desfeita quando e-mail ou telefone mudam
 * e histórico só de inserção (company_contact_events). Excluir = arquivar.
 */
final class CompanyContactService
{
    public const EDITABLE = [
        'name',
        'role',
        'email',
        'phone',
        'channel_email',
        'channel_whatsapp',
        'topics',
        'is_enabled',
        'is_primary',
        'responsible_user_id',
        'notes',
    ];

    /** Campos técnicos fora da auditoria de alteração. */
    private const NOT_AUDITED = ['phone_digits', 'updated_by', 'updated_at', 'created_at', 'reviewed_by'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, ?int $userId): CompanyContact
    {
        return DB::transaction(function () use ($company, $data, $userId): CompanyContact {
            $contact = new CompanyContact([
                ...$this->only($data),
                'company_id' => $company->id,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->guardPrimary($contact);
            $contact->save();

            $this->event($contact, CompanyContactEventType::Created, $userId);

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CompanyContact $contact, array $data, ?int $userId): CompanyContact
    {
        return DB::transaction(function () use ($contact, $data, $userId): CompanyContact {
            $contact->fill($this->only($data));
            $contact->updated_by = $userId;
            $this->guardPrimary($contact);
            $contact->save();

            $changed = array_values(array_diff(array_keys($contact->getChanges()), self::NOT_AUDITED));
            if ($changed !== []) {
                $this->event($contact, CompanyContactEventType::Updated, $userId, ['fields' => $changed]);
            }

            return $contact;
        });
    }

    /** «Conferir»: confirma que nome, contato, canais e tópicos foram verificados com a empresa. */
    public function review(CompanyContact $contact, ?int $userId): CompanyContact
    {
        if ($contact->isArchived()) {
            throw ValidationException::withMessages(['contact' => 'Restaure o contato antes de conferi-lo.']);
        }
        if ($contact->email === null && $contact->phone === null) {
            throw ValidationException::withMessages(['contact' => 'Informe e-mail ou telefone antes de conferir o contato.']);
        }

        return DB::transaction(function () use ($contact, $userId): CompanyContact {
            $contact->reviewed_at = now();
            $contact->reviewed_by = $userId;
            $contact->save();
            $this->event($contact, CompanyContactEventType::Reviewed, $userId);

            return $contact;
        });
    }

    public function archive(CompanyContact $contact, ?int $userId): CompanyContact
    {
        if ($contact->isArchived()) {
            return $contact;
        }

        return DB::transaction(function () use ($contact, $userId): CompanyContact {
            $contact->archived_at = now();
            $contact->is_primary = false;
            $contact->updated_by = $userId;
            $contact->save();
            $this->event($contact, CompanyContactEventType::Archived, $userId);

            return $contact;
        });
    }

    public function restore(CompanyContact $contact, ?int $userId): CompanyContact
    {
        if (! $contact->isArchived()) {
            return $contact;
        }

        return DB::transaction(function () use ($contact, $userId): CompanyContact {
            $contact->archived_at = null;
            $contact->updated_by = $userId;
            $contact->save();
            $this->event($contact, CompanyContactEventType::Restored, $userId);

            return $contact;
        });
    }

    /** Só um contato principal por empresa: marcar um desmarca os demais. Arquivado não pode ser principal. */
    private function guardPrimary(CompanyContact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }
        if ($contact->isArchived()) {
            throw ValidationException::withMessages(['is_primary' => 'Contato arquivado não pode ser o principal.']);
        }

        CompanyContact::query()
            ->where('company_id', $contact->company_id)
            ->when($contact->exists, fn ($q) => $q->whereKeyNot($contact->id))
            ->where('is_primary', true)
            ->lockForUpdate()
            ->get()
            ->each(function (CompanyContact $other) use ($contact): void {
                $other->is_primary = false;
                $other->updated_by = $contact->updated_by;
                $other->save();
                $this->event($other, CompanyContactEventType::Updated, $contact->updated_by, ['fields' => ['is_primary']]);
            });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function event(CompanyContact $contact, CompanyContactEventType $type, ?int $userId, array $meta = []): void
    {
        CompanyContactEvent::query()->create([
            'company_contact_id' => $contact->id,
            'type' => $type,
            'meta' => $meta === [] ? null : $meta,
            'user_id' => $userId,
        ]);
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
