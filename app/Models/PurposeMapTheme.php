<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurposeMapTheme extends Model
{
    protected $fillable = [
        'purpose_map_campaign_id',
        'question_key',
        'sector',
        'theme',
        'count',
        'sample_excerpts',
    ];

    protected function casts(): array
    {
        return [
            'sample_excerpts' => 'array',
            'count' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PurposeMapCampaign::class, 'purpose_map_campaign_id');
    }
}
