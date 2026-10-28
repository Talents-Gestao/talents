<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Models\SurveyAudienceLink;
use App\Models\SurveyInvitationToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gera convites de uso único (política duplicate_policy = one_time_token). Os códigos aparecem UMA vez
 * na saída; no banco fica só o hash, sem ligação com pessoa nem com resposta.
 */
class Nr1GenerateInvitesCommand extends Command
{
    protected $signature = 'nr1:generate-invites {survey : id da campanha} {count : quantidade de convites} {--audience= : time ou lideres (obrigatório quando a campanha tem links por público)}';

    protected $description = 'Gera convites de uso único para a pesquisa e imprime as URLs (o código não é guardado em claro).';

    public function handle(): int
    {
        $survey = Survey::query()->find((int) $this->argument('survey'));
        $count = (int) $this->argument('count');
        $audience = $this->option('audience') ?: null;

        if ($survey === null) {
            $this->error('Campanha não encontrada.');

            return self::FAILURE;
        }

        if ($count < 1 || $count > 5000) {
            $this->error('Informe uma quantidade entre 1 e 5000.');

            return self::FAILURE;
        }

        $link = null;
        if ($survey->audienceLinks()->exists()) {
            $link = $survey->audienceLinks()->where('audience', $audience)->first();
            if ($link === null) {
                $this->error('A campanha tem links por público: informe --audience (time ou lideres).');

                return self::FAILURE;
            }
        } elseif ($audience !== null && ! in_array($audience, SurveyAudienceLink::AUDIENCES, true)) {
            $this->error('Público inválido. Use time ou lideres.');

            return self::FAILURE;
        }

        $base = route('survey.public', ['token' => $link?->token ?? $survey->public_token]);

        for ($i = 0; $i < $count; $i++) {
            $code = Str::random(32);
            SurveyInvitationToken::query()->create([
                'survey_id' => $survey->id,
                'audience' => $audience,
                'token_hash' => SurveyInvitationToken::hashFor($code),
            ]);
            $this->line($base.'?convite='.$code);
        }

        return self::SUCCESS;
    }
}
