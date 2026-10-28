<?php

declare(strict_types=1);

namespace App\Http\Controllers\PurposeMap;

use App\Http\Controllers\Controller;
use App\Models\PurposeMapCampaign;
use App\Models\PurposeMapResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PublicPurposeMapController extends Controller
{
    public function thanks(string $token): InertiaResponse
    {
        $campaign = PurposeMapCampaign::query()->where('public_token', $token)->firstOrFail();

        return Inertia::render('PurposeMap/ThankYou', [
            'campaignTitle' => $campaign->title,
        ]);
    }

    public function show(string $token): InertiaResponse
    {
        $campaign = PurposeMapCampaign::query()
            ->where('public_token', $token)
            ->with('company:id,name')
            ->firstOrFail();

        $closedReason = $campaign->publicParticipationClosureReason();
        if ($closedReason !== null) {
            return Inertia::render('PurposeMap/Closed', [
                'message' => match ($closedReason) {
                    'inactive' => 'Este Mapa de Propósito não está ativo no momento.',
                    'not_started' => 'Este Mapa de Propósito ainda não iniciou.',
                    'ended' => 'Este Mapa de Propósito já foi encerrado.',
                    default => 'Este Mapa de Propósito não está ativo no momento.',
                },
            ]);
        }

        return Inertia::render('PurposeMap/Take', [
            'campaign' => [
                'title' => $campaign->title,
                'company_name' => $campaign->company?->name,
            ],
            'sectors' => config('purpose_map.sectors', []),
            'questions' => array_values(config('purpose_map.questions', [])),
            'maxAnswerLength' => (int) config('purpose_map.max_answer_length', 1000),
            'submitUrl' => route('purpose-map.public.submit', ['token' => $token]),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $campaign = PurposeMapCampaign::query()->where('public_token', $token)->firstOrFail();

        if (! $campaign->acceptsPublicResponses()) {
            abort(403);
        }

        $max = (int) config('purpose_map.max_answer_length', 1000);
        $sectors = config('purpose_map.sectors', []);

        $data = $request->validate([
            'sector' => ['required', 'string', Rule::in($sectors)],
            'why_work' => ['required', 'string', 'min:3', 'max:'.$max],
            'dream' => ['required', 'string', 'min:3', 'max:'.$max],
            'pain_self' => ['required', 'string', 'min:3', 'max:'.$max],
            'pain_sector' => ['required', 'string', 'min:3', 'max:'.$max],
            'pain_company' => ['required', 'string', 'min:3', 'max:'.$max],
        ]);

        $cookieName = 'purpose_map_'.$campaign->id;
        $cookieValue = $request->cookie($cookieName);
        if (! is_string($cookieValue) || strlen($cookieValue) < 16) {
            $cookieValue = Str::random(40);
        }

        $existing = PurposeMapResponse::query()
            ->where('purpose_map_campaign_id', $campaign->id)
            ->where('respondent_cookie', $cookieValue)
            ->exists();

        if ($existing) {
            return redirect()
                ->route('purpose-map.public.thanks', ['token' => $token])
                ->withCookie(Cookie::make($cookieName, $cookieValue, 60 * 24 * 90));
        }

        PurposeMapResponse::query()->create([
            'purpose_map_campaign_id' => $campaign->id,
            'sector' => $data['sector'],
            'why_work' => $data['why_work'],
            'dream' => $data['dream'],
            'pain_self' => $data['pain_self'],
            'pain_sector' => $data['pain_sector'],
            'pain_company' => $data['pain_company'],
            'respondent_cookie' => $cookieValue,
        ]);

        return redirect()
            ->route('purpose-map.public.thanks', ['token' => $token])
            ->withCookie(Cookie::make($cookieName, $cookieValue, 60 * 24 * 90));
    }
}
