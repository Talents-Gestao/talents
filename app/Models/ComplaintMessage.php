<?php

namespace App\Models;

use App\Concerns\SafelyDecryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintMessage extends Model
{
    use SafelyDecryptsAttributes;

    public const AUTHOR_REPORTER = 'reporter';

    public const AUTHOR_COMPANY = 'company';

    public const AUTHOR_SYSTEM = 'system';

    protected $fillable = [
        'complaint_id',
        'author_type',
        'user_id',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'encrypted',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
