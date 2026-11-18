<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Narrativa de Evolução de um colaborador entre feedbacks, gerada por IA a partir dos relatórios estruturados
 * (nunca de transcrições). Cada geração é uma linha nova; guarda as sessões usadas, para só ser mostrada a quem
 * vê todas elas.
 */
class FeedbackEvolutionReport extends Model
{
    use SafelyDecryptsAttributes;
    use SoftDeletes;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'company_employee_id',
        'session_ids',
        'report_ids',
        'status',
        'error',
        'report_json',
        'prompt_version',
        'schema_version',
        'provider',
        'model',
        'generated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'session_ids' => 'array',
            'report_ids' => 'array',
            'report_json' => 'encrypted:array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /** @return array<string, mixed>|null */
    public function safeReport(): ?array
    {
        $value = $this->safeDecrypt('report_json');

        return is_array($value) ? $value : null;
    }

    /** @return list<int> */
    public function sessionIds(): array
    {
        return array_values(array_map('intval', $this->session_ids ?? []));
    }
}
