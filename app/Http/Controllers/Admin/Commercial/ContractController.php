<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContract;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialProposal;
use App\Services\Commercial\ContractGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractGenerationService $generation,
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
}
