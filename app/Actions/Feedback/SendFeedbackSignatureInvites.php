<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Enums\FeedbackSessionStatus;
use App\Enums\FeedbackSignatureRole;
use App\Mail\FeedbackSignatureInvitationMail;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionSignature;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendFeedbackSignatureInvites
{
    public function execute(FeedbackSession $session): void
    {
        $session->load(['employee', 'leader']);

        $employee = $session->employee;
        $leader = $session->leader;

        abort_unless($employee && $leader, 422, 'Colaborador ou líder inválido.');

        $session->signatures()->delete();

        $pairs = [
            [
                'role' => FeedbackSignatureRole::Employee,
                'name' => $employee->name,
                'email' => $employee->email,
            ],
            [
                'role' => FeedbackSignatureRole::Leader,
                'name' => $leader->name,
                'email' => $leader->email,
            ],
        ];

        foreach ($pairs as $pair) {
            $signature = FeedbackSessionSignature::create([
                'feedback_session_id' => $session->id,
                'role' => $pair['role'],
                'signer_name' => $pair['name'],
                'signer_email' => $pair['email'],
                'token' => (string) Str::uuid(),
            ]);

            try {
                Mail::to($pair['email'])->send(new FeedbackSignatureInvitationMail($session, $signature));
                $signature->update(['sent_at' => now()]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $session->update(['status' => FeedbackSessionStatus::AwaitingSignatures]);
    }
}
