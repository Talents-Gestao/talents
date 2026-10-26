<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Services\Nr1CommercialSerializer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comercial limitado (módulo nr1_oportunidades): só campanhas liberadas e só o payload agregado do
 * Nr1CommercialSerializer. Não há exportação, download nem rota de resposta bruta neste controller.
 */
class Nr1OpportunityController extends Controller
{
    public function __construct(private readonly Nr1CommercialSerializer $serializer) {}

    public function index(): Response
    {
        $surveys = Survey::query()
            ->whereNotNull('commercial_released_at')
            ->with('company:id,name')
            ->orderByDesc('commercial_released_at')
            ->get()
            ->map(fn (Survey $survey) => $this->serializer->summary($survey));

        return Inertia::render('Admin/Nr1Opportunities/Index', ['surveys' => $surveys]);
    }

    public function show(int $survey): Response
    {
        $model = Survey::query()
            ->whereNotNull('commercial_released_at')
            ->with('company:id,name')
            ->find($survey);
        abort_if($model === null, 404);

        return Inertia::render('Admin/Nr1Opportunities/Show', [
            'opportunity' => $this->serializer->detail($model),
        ]);
    }
}
