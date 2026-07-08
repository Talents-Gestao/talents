<?php

namespace App\Http\Controllers\Complaint;

use App\Actions\Notices\PublishComplaintNotice;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Services\ComplaintAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicComplaintController extends Controller
{
    private function findCompany(string $token): Company
    {
        return Company::query()->where('complaints_public_token', $token)->firstOrFail();
    }

    public function create(string $token): Response
    {
        $company = $this->findCompany($token);
        $company->load(['departments' => fn ($q) => $q->orderBy('name')]);

        return Inertia::render('Complaint/Submit', [
            'token' => $token,
            'companyName' => $company->name,
            'departments' => $company->departments->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
            ])->values()->all(),
            'categories' => [
                'assedio_moral' => 'Assédio moral',
                'assedio_sexual' => 'Assédio sexual',
                'discriminacao' => 'Discriminação',
                'corrupcao' => 'Corrupção ou fraude',
                'seguranca' => 'Segurança no trabalho',
                'outros' => 'Outros',
            ],
        ]);
    }

    public function store(Request $request, string $token, PublishComplaintNotice $notices): RedirectResponse
    {
        $company = $this->findCompany($token);

        $data = $request->validate([
            'category' => ['required', 'string', 'in:assedio_moral,assedio_sexual,discriminacao,corrupcao,seguranca,outros'],
            'description' => ['required', 'string', 'min:20', 'max:20000'],
            'is_anonymous' => ['required', 'boolean'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $company->id),
            ],
        ]);

        if (! $data['is_anonymous']) {
            $request->validate([
                'reporter_name' => ['required', 'string', 'max:255'],
                'reporter_email' => ['required', 'email', 'max:255'],
            ]);
        }

        $complaint = Complaint::create([
            'company_id' => $company->id,
            'department_id' => $data['department_id'] ?? null,
            'protocol' => (string) Str::uuid(),
            'category' => $data['category'],
            'description' => $data['description'],
            'status' => 'new',
            'is_anonymous' => $data['is_anonymous'],
            'reporter_name' => $data['is_anonymous'] ? null : $data['reporter_name'],
            'reporter_email' => $data['is_anonymous'] ? null : $data['reporter_email'],
        ]);

        ComplaintAuditService::log($complaint, 'created', $request, null, [
            'category' => $data['category'],
            'is_anonymous' => $data['is_anonymous'],
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'author_type' => ComplaintMessage::AUTHOR_SYSTEM,
            'user_id' => null,
            'content' => 'Denúncia registrada com sucesso. Guarde o número de protocolo para acompanhar.',
        ]);

        $notices->created($complaint);

        return redirect()
            ->route('denuncia.thanks', ['token' => $token, 'protocol' => $complaint->protocol]);
    }

    public function thanks(string $token, string $protocol): Response
    {
        $company = $this->findCompany($token);
        $complaint = Complaint::query()
            ->where('company_id', $company->id)
            ->where('protocol', $protocol)
            ->firstOrFail();

        return Inertia::render('Complaint/Thanks', [
            'token' => $token,
            'companyName' => $company->name,
            'protocol' => $complaint->protocol,
        ]);
    }

    public function track(string $token): Response
    {
        $company = $this->findCompany($token);

        return Inertia::render('Complaint/Track', [
            'token' => $token,
            'companyName' => $company->name,
        ]);
    }

    public function trackLookup(Request $request, string $token): RedirectResponse
    {
        $company = $this->findCompany($token);
        $data = $request->validate([
            'protocol' => ['required', 'uuid'],
        ]);

        $exists = Complaint::query()
            ->where('company_id', $company->id)
            ->where('protocol', $data['protocol'])
            ->exists();

        if (! $exists) {
            return back()->withErrors(['protocol' => 'Protocolo não encontrado.']);
        }

        return redirect()->route('denuncia.protocol', ['token' => $token, 'protocol' => $data['protocol']]);
    }

    public function showProtocol(Request $request, string $token, string $protocol): Response
    {
        $company = $this->findCompany($token);
        $complaint = Complaint::query()
            ->where('company_id', $company->id)
            ->where('protocol', $protocol)
            ->with([
                'department',
                'messages' => fn ($q) => $q->orderBy('id'),
            ])
            ->firstOrFail();

        ComplaintAuditService::log($complaint, 'viewed_by_reporter', $request, null);

        return Inertia::render('Complaint/ReporterView', [
            'token' => $token,
            'companyName' => $company->name,
            'complaint' => [
                'protocol' => $complaint->protocol,
                'category' => $complaint->category,
                'status' => $complaint->status,
                'department_name' => $complaint->department?->name,
                'description' => $complaint->safeDecrypt('description', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                'created_at' => $complaint->created_at?->toIso8601String(),
                'messages' => $complaint->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'author_type' => $m->author_type,
                    'content' => $m->safeDecrypt('content', Complaint::UNREADABLE_ENCRYPTED_PLACEHOLDER),
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function reporterMessage(Request $request, string $token, string $protocol): RedirectResponse
    {
        $company = $this->findCompany($token);
        $complaint = Complaint::query()
            ->where('company_id', $company->id)
            ->where('protocol', $protocol)
            ->firstOrFail();

        $data = $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'author_type' => ComplaintMessage::AUTHOR_REPORTER,
            'user_id' => null,
            'content' => $data['content'],
        ]);

        ComplaintAuditService::log($complaint, 'message_added_by_reporter', $request, null);

        return back()->with('success', 'Mensagem enviada.');
    }
}
