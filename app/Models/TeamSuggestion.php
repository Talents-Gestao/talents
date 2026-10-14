<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSuggestion extends Model
{
    use SafelyDecryptsAttributes;

    public const UNREADABLE_ENCRYPTED_PLACEHOLDER = '[Conteúdo indisponível — chave de criptografia alterada]';

    protected $fillable = [
        'company_id',
        'topic',
        'area',
        'message',
        'response_preference',
        'reporter_name',
        'contact',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'message' => 'encrypted',
            'reporter_name' => 'encrypted',
            'contact' => 'encrypted',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
