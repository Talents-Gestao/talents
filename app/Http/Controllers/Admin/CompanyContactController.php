<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CompanyContactTopic;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\TalentCandidate;
use App\Models\User;
use App\Services\Hiring\CompanyContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contatos da empresa (aba «Contatos» do detalhe em Clientes). Ver: companies:view; criar, editar,
 * conferir, arquivar e restaurar: companies:edit. Rotas com scopeBindings: contato de outra empresa dá 404.
 */
class CompanyContactController extends Controller
{
    public function __construct(
        private readonly CompanyContactService $service,
    ) {}

    public function store(Request $request, Company $company): RedirectResponse
    {
        $this->service->create($company, $this->validated($request), $request->user()?->id);

        return $this->backToTab($company, 'Contato criado. Confira-o antes de usá-lo em comunicações.');
    }

    public function update(Request $request, Company $company, CompanyContact $contact): RedirectResponse
    {
        $updated = $this->service->update($contact, $this->validated($request), $request->user()?->id);

        return $this->backToTab($company, $updated->isReviewed()
            ? 'Contato atualizado.'
            : 'Contato atualizado. Ele precisa ser conferido para receber comunicações.');
    }

    public function review(Request $request, Company $company, CompanyContact $contact): RedirectResponse
    {
        $this->service->review($contact, $request->user()?->id);

        return $this->backToTab($company, 'Contato conferido.');
    }

    public function archive(Request $request, Company $company, CompanyContact $contact): RedirectResponse
    {
        $this->service->archive($contact, $request->user()?->id);

        return $this->backToTab($company, 'Contato arquivado. O histórico foi preservado.');
    }

    public function restore(Request $request, Company $company, CompanyContact $contact): RedirectResponse
    {
        $this->service->restore($contact, $request->user()?->id);

        return $this->backToTab($company, 'Contato restaurado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::requiredIf($request->boolean('channel_email'))],
            'phone' => ['nullable', 'string', 'max:40', Rule::requiredIf($request->boolean('channel_whatsapp')), function (string $attribute, mixed $value, \Closure $fail): void {
                if (! TalentCandidate::isValidInternationalPhone((string) $value)) {
                    $fail('Informe o telefone no formato internacional, começando com + e o código do país (ex.: +55 11 91234-5678).');
                }
            }],
            'channel_email' => ['boolean'],
            'channel_whatsapp' => ['boolean'],
            'topics' => ['nullable', 'array'],
            'topics.*' => [Rule::enum(CompanyContactTopic::class)],
            'is_enabled' => ['boolean'],
            'is_primary' => ['boolean'],
            'responsible_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && ! User::query()->whereKey((int) $value)->where('role', UserRole::SuperAdmin->value)->exists()) {
                    $fail('O responsável precisa ser da equipe Talents.');
                }
            }],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.required' => 'Informe o e-mail para autorizar o canal e-mail.',
            'phone.required' => 'Informe o telefone para autorizar o canal WhatsApp.',
        ], [
            'name' => 'nome',
            'role' => 'função',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'topics' => 'tópicos',
            'responsible_user_id' => 'responsável Talents',
            'notes' => 'observações',
        ]);

        foreach (['channel_email', 'channel_whatsapp', 'is_enabled', 'is_primary'] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }
        $data['topics'] = array_values(array_unique($data['topics'] ?? []));

        return $data;
    }

    private function backToTab(Company $company, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.companies.show', ['company' => $company, 'tab' => 'contatos'])
            ->with('success', $message);
    }
}
