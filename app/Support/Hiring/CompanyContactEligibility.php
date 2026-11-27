<?php

declare(strict_types=1);

namespace App\Support\Hiring;

use App\Enums\CompanyContactTopic;
use App\Enums\HiringCommunicationChannel;
use App\Models\CompanyContact;
use Illuminate\Support\Collection;

/**
 * Regra única de quem pode receber comunicação da Talents: contato habilitado, conferido, não arquivado,
 * com o tópico pedido e o canal pedido (marcado e com o dado preenchido: e-mail ou telefone).
 * Toda tela, rota e envio usa esta classe; nenhum outro ponto repete a regra.
 */
final class CompanyContactEligibility
{
    /**
     * Motivos que impedem a comunicação (lista vazia = elegível).
     *
     * @return list<string>
     */
    public static function reasons(CompanyContact $contact, CompanyContactTopic $topic, HiringCommunicationChannel $channel): array
    {
        $reasons = [];

        if ($contact->isArchived()) {
            $reasons[] = 'Contato arquivado.';
        }
        if (! $contact->is_enabled) {
            $reasons[] = 'Contato desabilitado.';
        }
        if (! $contact->isReviewed()) {
            $reasons[] = 'Contato ainda não conferido.';
        }
        if (! in_array($topic->value, $contact->topicValues(), true)) {
            $reasons[] = 'Contato não aceita o tópico «'.$topic->label().'».';
        }

        if ($channel === HiringCommunicationChannel::Email) {
            if (! $contact->channel_email) {
                $reasons[] = 'Canal e-mail não autorizado.';
            } elseif ($contact->email === null) {
                $reasons[] = 'Contato sem e-mail.';
            }
        } else {
            if (! $contact->channel_whatsapp) {
                $reasons[] = 'Canal WhatsApp não autorizado.';
            } elseif ($contact->phone_digits === null) {
                $reasons[] = 'Contato sem telefone.';
            }
        }

        return $reasons;
    }

    public static function isEligible(CompanyContact $contact, CompanyContactTopic $topic, HiringCommunicationChannel $channel): bool
    {
        return self::reasons($contact, $topic, $channel) === [];
    }

    /**
     * Contatos elegíveis da empresa para o tópico e o canal.
     *
     * @return Collection<int, CompanyContact>
     */
    public static function eligibleFor(int $companyId, CompanyContactTopic $topic, HiringCommunicationChannel $channel): Collection
    {
        return CompanyContact::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get()
            ->filter(fn (CompanyContact $c) => self::isEligible($c, $topic, $channel))
            ->values();
    }

    /**
     * Canais em que o contato pode receber o tópico (para a tela).
     *
     * @return list<string>
     */
    public static function channelsFor(CompanyContact $contact, CompanyContactTopic $topic): array
    {
        return array_values(array_map(
            static fn (HiringCommunicationChannel $c) => $c->value,
            array_filter(
                HiringCommunicationChannel::cases(),
                static fn (HiringCommunicationChannel $c) => self::isEligible($contact, $topic, $c),
            ),
        ));
    }
}
