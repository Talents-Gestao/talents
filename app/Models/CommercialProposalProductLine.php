<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialProposalProductLine extends Model
{
    protected $fillable = [
        'commercial_proposal_id',
        'commercial_product_id',
        'options',
        'label_snapshot',
        'detail_snapshot',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'total_cents' => 'integer',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(CommercialProposal::class, 'commercial_proposal_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CommercialProduct::class, 'commercial_product_id');
    }
}
