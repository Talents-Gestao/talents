<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurposeMapResponse extends Model
{
    protected $fillable = [
        'purpose_map_campaign_id',
        'sector',
        'why_work',
        'dream',
        'pain_self',
        'pain_sector',
        'pain_company',
        'respondent_cookie',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PurposeMapCampaign::class, 'purpose_map_campaign_id');
    }
}
