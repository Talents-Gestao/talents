<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Mfa\MfaService;
use Illuminate\Console\Command;

/**
 * Reset de emergência da verificação em duas etapas de um usuário (quando ninguém mais consegue entrar). Registra a
 * auditoria (sem ator humano) e avisa o usuário por e-mail.
 */
class MfaResetCommand extends Command
{
    protected $signature = 'mfa:reset {email : e-mail do usuário}';

    protected $description = 'Reset de emergência do MFA de um usuário (apaga o segredo e os códigos de recuperação).';

    public function handle(MfaService $mfa): int
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $this->argument('email')))])->first();

        if ($user === null) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $mfa->resetByCommand($user);
        $this->info("Verificação em duas etapas de {$user->email} resetada. O usuário foi avisado por e-mail.");

        return self::SUCCESS;
    }
}
