<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContract;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Services\Commercial\CommercialProposalServiceLines;
use App\Services\Commercial\ContractGenerationService;
use App\Services\Commercial\ZapSignService;
use App\Support\BrazilMobilePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractGenerationService $generation,
        private readonly ZapSignService $zapSign,
    ) {}

    public function store(Request $request, CommercialProposal $proposal): RedirectResponse
    {
        $request->validate([
            'template_id' => [
                'required',
                'integer',
                Rule::exists('commercial_contract_templates', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        $template = CommercialContractTemplate::query()
            ->whereKey($request->integer('template_id'))
            ->where('is_active', true)
            ->firstOrFail();

        $proposal->loadMissing('catalogLines');
        if (CommercialProposalServiceLines::forProposal($proposal) === []) {
            return redirect()
                ->back()
                ->with('error', 'Não é possível gerar o contrato: a proposta não tem serviços contratados. Edite a proposta, selecione os produtos (ou preencha o valor recorrente) e gere novamente.');
        }

        $contract = $this->generation->generate($template, $proposal, $request->user());

        return redirect()
            ->back()
            ->with('success', "Contrato {$contract->code} gerado.")
            ->with('contract_id', $contract->id);
    }

    public function pdf(CommercialContract $contract): BinaryFileResponse
    {
        if (! Storage::disk('local')->exists($contract->pdf_path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($contract->pdf_path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$contract->code.'.pdf"',
            ],
        );
    }

    public function sendZapSign(Request $request, CommercialContract $contract): RedirectResponse
    {
        $contract->load(['proposal', 'template']);

        if ($contract->zapsign_document_token) {
            return redirect()
                ->back()
                ->with('error', 'Este contrato já foi enviado ao ZapSign.')
                ->with('zapsign_sign_url', $contract->zapsign_primary_sign_url);
        }

        $settings = CommercialSetting::current();
        if (! filled(trim((string) ($settings->zapsign_api_token ?? '')))) {
            return redirect()
                ->back()
                ->with('error', 'Configure o token da ZapSign em Comercial → Configurações → PDF.');
        }

        $proposal = $contract->proposal;
        if (! $proposal) {
            return redirect()->back()->with('error', 'Proposta não encontrada para este contrato.');
        }

        $clientEmail = trim((string) ($proposal->client_email ?? ''));
        $clientName = trim((string) ($proposal->client_representative ?: $proposal->client_name ?: ''));
        $companyEmail = trim((string) ($settings->company_email ?? ''));
        $companySigner = trim((string) ($settings->company_contract_signatory_name ?? ''));

        $clientPhone = BrazilMobilePhone::parse($proposal->client_phone ?? null);
        $companyPhone = BrazilMobilePhone::parse($settings->company_phone ?? null);

        $clientEmailOk = $clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL);
        $companyEmailOk = $companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL);

        if ($clientName === '') {
            return redirect()->back()->with('error', 'Preencha o nome do cliente ou representante legal (CONTRATANTE) na proposta.');
        }
        if ($companySigner === '') {
            return redirect()->back()->with('error', 'Configure o nome do signatário da CONTRATADA (ex.: Suzane) em Empresa Talents.');
        }
        if ($clientPhone === null && ! $clientEmailOk) {
            return redirect()->back()->with('error', 'Para o ZapSign, cadastre na proposta um e-mail válido do cliente OU um celular/WhatsApp com DDD (ex.: 11 98765-4321). Com celular, o link de assinatura é enviado por WhatsApp pela ZapSign.');
        }
        if ($companyPhone === null && ! $companyEmailOk) {
            return redirect()->back()->with('error', 'Para o ZapSign, configure em Empresa Talents um e-mail institucional OU o telefone/WhatsApp (com DDD) da Talents para receber o link da CONTRATADA.');
        }

        if (! Storage::disk('local')->exists($contract->pdf_path)) {
            return redirect()->back()->with('error', 'Arquivo PDF do contrato não encontrado. Gere o contrato novamente.');
        }

        $pdfBinary = Storage::disk('local')->get($contract->pdf_path);
        $base64 = base64_encode($pdfBinary);
        $docName = $contract->code.' — '.($contract->template?->name ?? 'Contrato');

        $autoEmail = (bool) ($settings->zapsign_send_automatic_email ?? true);

        $signers = [
            $this->zapSignSignerPayload($clientName, $clientEmail, $clientPhone, 1, $autoEmail),
            $this->zapSignSignerPayload($companySigner, $companyEmail, $companyPhone, 2, $autoEmail),
        ];

        try {
            $result = $this->zapSign->createDocumentFromPdfBase64(
                $docName,
                $base64,
                $signers,
                'commercial_contract:'.$contract->id,
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $primaryUrl = null;
        if (! empty($result['signers'][0]) && is_array($result['signers'][0])) {
            $primaryUrl = $result['signers'][0]['sign_url'] ?? null;
        }
        $primaryUrl = is_string($primaryUrl) ? $primaryUrl : null;

        $contract->zapsign_document_token = $result['token'];
        $contract->zapsign_status = $result['status'];
        $contract->zapsign_sent_at = now();
        $contract->zapsign_primary_sign_url = $primaryUrl;
        $contract->save();

        $msg = 'Contrato enviado ao ZapSign. 1º signatário (CONTRATANTE): '
            .($clientPhone !== null ? 'link por WhatsApp (ZapSign).' : 'link por e-mail (se habilitado nas configurações).')
            .' 2º signatário (CONTRATADA — '.$companySigner.'): '
            .($companyPhone !== null ? 'WhatsApp.' : 'e-mail.');

        return redirect()
            ->back()
            ->with('success', $msg)
            ->with('zapsign_sign_url', $primaryUrl);
    }

    /**
     * Se houver celular válido, usa envio/autenticação por WhatsApp (prioridade sobre e-mail).
     * Caso contrário usa e-mail com token.
     *
     * @param  array{country: string, national: string}|null  $parsedPhone
     * @return array<string, mixed>
     */
    private function zapSignSignerPayload(
        string $name,
        string $email,
        ?array $parsedPhone,
        int $orderGroup,
        bool $settingsAutoEmail,
    ): array {
        if ($parsedPhone !== null) {
            return [
                'name' => $name,
                'email' => '',
                'blank_email' => true,
                'phone_country' => $parsedPhone['country'],
                'phone_number' => $parsedPhone['national'],
                'auth_mode' => 'assinaturaTela-tokenWhatsapp',
                'send_automatic_whatsapp' => true,
                'send_automatic_email' => false,
                'order_group' => $orderGroup,
            ];
        }

        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Signatário sem e-mail válido.');
        }

        return [
            'name' => $name,
            'email' => $email,
            'auth_mode' => 'assinaturaTela-tokenEmail',
            'send_automatic_email' => $settingsAutoEmail,
            'send_automatic_whatsapp' => false,
            'order_group' => $orderGroup,
        ];
    }
}
