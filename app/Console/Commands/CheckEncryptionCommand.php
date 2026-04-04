<?php

namespace App\Console\Commands;

use App\Models\AiSetting;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\MailSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckEncryptionCommand extends Command
{
    protected $signature = 'app:check-encryption';

    protected $description = 'Verifica se os campos criptografados no banco podem ser lidos com a APP_KEY atual';

    public function handle(): int
    {
        $definitions = [
            [AiSetting::class, ['api_key']],
            [MailSetting::class, ['password']],
            [Complaint::class, ['description', 'reporter_name', 'reporter_email']],
            [ComplaintMessage::class, ['content']],
        ];

        foreach ($definitions as [$class, $attrs]) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                $this->line(sprintf('%s: tabela não existe (ignorado)', class_basename($class)));

                continue;
            }

            $totalRecords = $class::query()->count();
            $ok = 0;
            $fail = 0;

            foreach ($class::query()->cursor() as $m) {
                foreach ($attrs as $attr) {
                    if (! $m->hasStoredEncrypted($attr)) {
                        continue;
                    }
                    if ($m->canDecrypt($attr)) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                }
            }

            $this->line(sprintf(
                '%s: %d registro(s), %d verificação(ões) OK, %d falha(s)',
                class_basename($class),
                $totalRecords,
                $ok,
                $fail
            ));
        }

        return self::SUCCESS;
    }
}
