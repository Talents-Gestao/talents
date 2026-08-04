<?php

namespace App\Http\Controllers;

use App\Actions\Leads\CreateLandingInterestSubmission;
use App\Enums\LandingInterestSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingInterestController extends Controller
{
    public function store(Request $request, CreateLandingInterestSubmission $create): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'string', Rule::enum(LandingInterestSource::class)],
        ]);

        $create->execute($data);

        return back()->with('success', 'Recebemos seu interesse. Em breve entraremos em contato.');
    }
}
