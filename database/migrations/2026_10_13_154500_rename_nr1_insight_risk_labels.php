<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceInsightPrefixes([
            'Risco intermediário:' => 'Moderado:',
            'Intermediário:' => 'Moderado:',
            'Risco elevado:' => 'Grave:',
            'Situação favorável:' => 'Leve:',
        ]);
    }

    public function down(): void
    {
        $this->replaceInsightPrefixes([
            'Moderado:' => 'Intermediário:',
        ]);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceInsightPrefixes(array $replacements): void
    {
        DB::table('survey_insights')->orderBy('id')->chunkById(200, function ($rows) use ($replacements): void {
            foreach ($rows as $row) {
                $message = (string) $row->message;
                $updated = str_replace(array_keys($replacements), array_values($replacements), $message);

                if ($updated === $message) {
                    continue;
                }

                DB::table('survey_insights')->where('id', $row->id)->update([
                    'message' => $updated,
                ]);
            }
        });
    }
};
