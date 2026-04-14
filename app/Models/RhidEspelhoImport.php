<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhidEspelhoImport extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'id_person',
        'period_ini',
        'period_fim',
        'guid',
        'storage_path',
        'file_hash',
        'source',
        'parse_status',
        'parse_error',
        'parsed_at',
        'raw_extract_json',
    ];

    protected function casts(): array
    {
        return [
            'id_person' => 'integer',
            'period_ini' => 'date',
            'period_fim' => 'date',
            'parsed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(RhidEspelhoDay::class, 'import_id');
    }
}
