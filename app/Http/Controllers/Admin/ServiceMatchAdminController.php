<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Nr1ServiceMatch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regras «dimensão em atenção → serviço Talents» (matching configurável): serviço, serviços elegíveis, explicação
 * consultiva, grau de confiança e a revisão pela Talents. Mudar o texto de uma regra revisada exige nova revisão.
 */
class ServiceMatchAdminController extends Controller
{
    private const TEXT_FIELDS = ['service_name', 'eligible_services', 'consultative_text'];

    public function index(): Response
    {
        return Inertia::render('Admin/ServiceMatches/Index', [
            'matches' => Nr1ServiceMatch::query()->with('reviewer:id,name')->orderBy('sort_order')->get()->map(fn (Nr1ServiceMatch $match) => [
                'id' => $match->id,
                'factor_label' => $match->factor_label,
                'service_name' => $match->service_name,
                'eligible_services' => $match->eligible_services,
                'consultative_text' => $match->consultative_text,
                'confidence' => $match->confidence,
                'active' => $match->active,
                'commercial_owner_id' => $match->commercial_owner_id,
                'reviewed' => $match->isReviewed(),
                'reviewed_at' => $match->reviewed_at?->format('d/m/Y H:i'),
                'reviewed_by' => $match->reviewer?->name,
            ]),
            'confidenceLabels' => Nr1ServiceMatch::CONFIDENCE_LABELS,
            'owners' => User::query()->where('role', UserRole::SuperAdmin)->orderBy('name')->get(['id', 'name']),
            'requireReview' => (bool) config('nr1.solutions.require_review', true),
        ]);
    }

    public function update(Request $request, Nr1ServiceMatch $match): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string', 'max:255'],
            'eligible_services' => ['required', 'string', 'max:500'],
            'consultative_text' => ['required', 'string', 'max:2000'],
            'confidence' => ['required', Rule::in(array_keys(Nr1ServiceMatch::CONFIDENCE_LABELS))],
            'active' => ['required', 'boolean'],
            'commercial_owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::SuperAdmin->value)],
        ]);

        $match->fill($data);

        // Texto ou serviço mudou: a revisão anterior não vale mais para a nova versão.
        if ($match->isDirty(self::TEXT_FIELDS) || $match->isDirty('confidence')) {
            $match->reviewed_at = null;
            $match->reviewed_by = null;
        }

        $match->save();

        return back()->with('success', 'Regra salva'.($match->isReviewed() ? '.' : '. Ela volta a exigir revisão da Talents antes de aparecer para a empresa.'));
    }

    public function approve(Request $request, Nr1ServiceMatch $match): RedirectResponse
    {
        $match->forceFill(['reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();

        return back()->with('success', 'Texto revisado e liberado para as empresas.');
    }
}
