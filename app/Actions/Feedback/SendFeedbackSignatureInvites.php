<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Actions\Notices\PublishFeedbackNotice;
use App\Enums\FeedbackSessionStatus;
use App\Enums\FeedbackSignatureRole;
use App\Mail\FeedbackSignatureInvitationMail;
use App\Models\FeedbackSession;
use App\Models\FeedbackSessionSignature;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendFeedbackSignatureInvites
{
    public function __construct(
        private readonly PublishFeedbackNotice $notices,
    ) {}

    public function execute(FeedbackSession $session, ?User $actor = null): void
    {
        $session->load(['employee', 'leader']);

        $leader = $session->leader;
        $employeeName = $session->collaboratorDisplayName();
        $employeeEmail = $session->collaboratorEmail();

        abort_unless($leader, 422, 'Líder inválido.');
        abort_unless(
            filled($employeeEmail),
            422,
            'Colaborador sem e-mail no RHID/Control iD. Atualize o cadastro da pessoa ou complete o e-mail antes de enviar assinaturas.',
        );

        $session->signatures()->delete();

        $pairs = [
            [
                'role' => FeedbackSignatureRole::Employee,
                'name' => $employeeName,
                'email' => $employeeEmail,
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

        $this->notices->awaitingSignature($session, $actor);
    }
}
