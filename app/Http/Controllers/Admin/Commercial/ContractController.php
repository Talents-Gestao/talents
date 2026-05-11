<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContract;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProposal;
use App\Models\CommercialSetting;
use App\Services\Commercial\ContractGenerationService;
use App\Services\Commercial\ZapSignService;
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

        if ($clientEmail === '' || ! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Informe um e-mail válido do cliente na proposta antes de enviar ao ZapSign.');
        }
        if ($companyEmail === '' || ! filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Configure o e-mail da empresa Talents (Empresa Talents no menu) para o signatário CONTRATADA.');
        }
        if ($clientName === '') {
            return redirect()->back()->with('error', 'Preencha o nome do cliente ou representante na proposta.');
        }
        if ($companySigner === '') {
            return redirect()->back()->with('error', 'Configure o nome do signatário da CONTRATADA em Empresa Talents.');
        }

        if (! Storage::disk('local')->exists($contract->pdf_path)) {
            return redirect()->back()->with('error', 'Arquivo PDF do contrato não encontrado. Gere o contrato novamente.');
        }

        $pdfBinary = Storage::disk('local')->get($contract->pdf_path);
        $base64 = base64_encode($pdfBinary);
        $docName = $contract->code.' — '.($contract->template?->name ?? 'Contrato');

        $signers = [
            [
                'name' => $clientName,
                'email' => $clientEmail,
                'order_group' => 1,
            ],
            [
                'name' => $companySigner,
                'email' => $companyEmail,
                'order_group' => 2,
            ],
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

        return redirect()
            ->back()
            ->with('success', 'Contrato enviado ao ZapSign. O primeiro signatário receberá o link por e-mail (se habilitado).')
            ->with('zapsign_sign_url', $primaryUrl);
    }
}
