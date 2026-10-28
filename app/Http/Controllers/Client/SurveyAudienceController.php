<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Links por público (Time e Líderes) da campanha: criação, janela de abertura e QR code.
 * O link só abre dentro da janela; fora dela a página pública mostra "indisponível".
 */
class SurveyAudienceController extends Controller
{
    private function surveyOf(Request $request, Survey $survey): Survey
    {
        abort_unless((int) $survey->company_id === (int) $request->user()->company_id, 404);

        return $survey;
    }

    private function linkOf(Request $request, Survey $survey, SurveyAudienceLink $link): SurveyAudienceLink
    {
        $this->surveyOf($request, $survey);
        abort_unless((int) $link->survey_id === (int) $survey->id, 404);

        return $link;
    }

    public function store(Request $request, Survey $survey): RedirectResponse
    {
        $this->surveyOf($request, $survey);

        foreach (SurveyAudienceLink::AUDIENCES as $audience) {
            SurveyAudienceLink::query()->firstOrCreate(
                ['survey_id' => $survey->id, 'audience' => $audience],
                ['token' => Str::random(40), 'enabled' => true],
            );
        }

        return back()->with('success', 'Links por público criados.');
    }

    public function update(Request $request, Survey $survey, SurveyAudienceLink $link): RedirectResponse
    {
        $link = $this->linkOf($request, $survey, $link);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ]);

        $link->update($data);

        return back()->with('success', 'Link atualizado.');
    }

    public function qr(Request $request, Survey $survey, SurveyAudienceLink $link): Response
    {
        $link = $this->linkOf($request, $survey, $link);

        // Campanha NR-1 V11: o QR só existe enquanto a campanha e o link estão abertos.
        abort_if($survey->usesReviewFlow() && ($survey->publicParticipationClosureReason() !== null || $link->closureReason() !== null), 404);

        return $this->svg(route('survey.public', ['token' => $link->token]));
    }

    private function svg(string $url): Response
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 1), new SvgImageBackEnd));

        return response($writer->writeString($url), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
