<?php

namespace App\Console\Commands;

use App\Models\SurveyInstrumentVersion;
use App\Models\User;
use App\Support\Nr1InstrumentMatrix;
use Illuminate\Console\Command;

/**
 * Registra a homologação da polaridade de uma versão de instrumento pela responsável técnica.
 * Enquanto não for aceita, os documentos AEP/PGR da campanha não são liberados.
 */
class Nr1AcceptPolarityCommand extends Command
{
    protected $signature = 'nr1:accept-polarity {instrumentVersion : id de survey_instrument_versions} {--user= : id do usuário que registra a homologação} {--reference= : referência do documento de homologação}';

    protected $description = 'Registra a homologação da polaridade de um instrumento (libera os documentos AEP/PGR).';

    public function handle(): int
    {
        $version = SurveyInstrumentVersion::query()->find((int) $this->argument('instrumentVersion'));
        $user = User::query()->find((int) $this->option('user'));

        if ($version === null) {
            $this->error('Versão de instrumento não encontrada.');

            return self::FAILURE;
        }

        if ($user === null) {
            $this->error('Informe --user com o id de quem registra a homologação.');

            return self::FAILURE;
        }

        if ($version->polarity_audit_status === Nr1InstrumentMatrix::AUDIT_ACCEPTED) {
            $this->info('A polaridade desta versão já está aceita.');

            return self::SUCCESS;
        }

        $version->update([
            'polarity_audit_status' => Nr1InstrumentMatrix::AUDIT_ACCEPTED,
            'polarity_audit_accepted_at' => now(),
            'polarity_audit_accepted_by' => $user->id,
            'validation_reference' => $this->option('reference') ?: $version->validation_reference,
        ]);

        $this->info("Polaridade aceita para a versão #{$version->id} por {$user->name}.");

        return self::SUCCESS;
    }
}
