<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RhidEspelhoDay extends Model
{
    protected $fillable = [
        'import_id',
        'ref_date',
        'row_json',
    ];

    protected function casts(): array
    {
        return [
            'ref_date' => 'date',
            'row_json' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(RhidEspelhoImport::class, 'import_id');
    }
}
