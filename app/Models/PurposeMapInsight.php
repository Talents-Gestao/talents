<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurposeMapInsight extends Model
{
    protected $fillable = [
        'purpose_map_campaign_id',
        'kind',
        'message',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PurposeMapCampaign::class, 'purpose_map_campaign_id');
    }
}
