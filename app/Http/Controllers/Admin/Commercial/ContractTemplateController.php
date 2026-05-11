<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialContractTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContractTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $template = CommercialContractTemplate::create([
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'body_html' => $data['source_type'] === 'html' ? ($data['body_html'] ?? '') : null,
            'docx_path' => null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        if ($data['source_type'] === 'docx' && $request->hasFile('docx_file')) {
            $path = $request->file('docx_file')->storeAs(
                "contract-templates/{$template->id}",
                'original.docx',
                'local',
            );
            $template->update(['docx_path' => $path, 'body_html' => null]);
        }

        return redirect()
            ->to(route('admin.comercial.settings.edit').'?tab=contratos')
            ->with('success', 'Modelo de contrato criado.');
    }

    public function update(Request $request, CommercialContractTemplate $template): RedirectResponse
    {
        $data = $this->validated($request, $template);

        $template->fill([
            'name' => $data['name'],
            'source_type' => $data['source_type'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $template->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        if ($data['source_type'] === 'html') {
            $template->body_html = $data['body_html'] ?? '';
            $template->docx_path = null;
            if ($template->getOriginal('docx_path')) {
                Storage::disk('local')->deleteDirectory('contract-templates/'.$template->id);
            }
        } else {
            $template->body_html = null;
            if ($request->hasFile('docx_file')) {
                Storage::disk('local')->deleteDirectory('contract-templates/'.$template->id);
                $path = $request->file('docx_file')->storeAs(
                    "contract-templates/{$template->id}",
                    'original.docx',
                    'local',
                );
                $template->docx_path = $path;
            }
        }

        $template->save();

        return redirect()
            ->to(route('admin.comercial.settings.edit').'?tab=contratos')
            ->with('success', 'Modelo atualizado.');
    }

    public function destroy(CommercialContractTemplate $template): RedirectResponse
    {
        Storage::disk('local')->deleteDirectory('contract-templates/'.$template->id);
        $template->delete();

        return redirect()
            ->to(route('admin.comercial.settings.edit').'?tab=contratos')
            ->with('success', 'Modelo removido.');
    }

    public function downloadDocx(CommercialContractTemplate $template)
    {
        if (! $template->docx_path || ! Storage::disk('local')->exists($template->docx_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($template->docx_path, $template->name.'-original.docx');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CommercialContractTemplate $existing): array
    {
        $unique = Rule::unique('commercial_contract_templates', 'name');
        if ($existing) {
            $unique = $unique->ignore($existing->id);
        }

        $docxRules = ['nullable', 'file', 'mimes:docx', 'max:5120'];
        if ($request->input('source_type') === 'docx') {
            $hasExistingDocx = $existing
                && $existing->docx_path
                && Storage::disk('local')->exists($existing->docx_path);
            if (! $hasExistingDocx) {
                $docxRules = ['required', 'file', 'mimes:docx', 'max:5120'];
            }
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $unique],
            'source_type' => ['required', Rule::in(['html', 'docx'])],
            'body_html' => ['nullable', 'string', 'required_if:source_type,html'],
            'docx_file' => $docxRules,
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
