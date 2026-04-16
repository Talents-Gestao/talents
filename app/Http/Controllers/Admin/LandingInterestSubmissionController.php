<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingInterestSubmission;
use Inertia\Inertia;
use Inertia\Response;

class LandingInterestSubmissionController extends Controller
{
    public function index(): Response
    {
        $submissions = LandingInterestSubmission::query()
            ->orderByDesc('id')
            ->paginate(30)
            ->through(fn (LandingInterestSubmission $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'company' => $s->company,
                'message' => $s->message,
                'mail_sent_at' => $s->mail_sent_at?->toIso8601String(),
                'mail_error' => $s->mail_error,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/LandingInterest/Index', [
            'submissions' => $submissions,
        ]);
    }
}
