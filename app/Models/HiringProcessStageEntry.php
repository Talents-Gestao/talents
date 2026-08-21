<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HiringProcessStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HiringProcessStageEntry extends Model
{
    protected $fillable = [
        'hiring_process_id',
        'stage',
        'notes',
        'candidates_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => HiringProcessStage::class,
            'candidates_count' => 'integer',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(HiringProcess::class, 'hiring_process_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array{
     *     id: int,
     *     stage: string,
     *     stage_label: string,
     *     stage_order: int,
     *     notes: string|null,
     *     candidates_count: int|null,
     *     created_by_name: string|null,
     *     updated_at: string|null
     * }
     */
    public function toFrontend(): array
    {
        $stage = $this->stage instanceof HiringProcessStage
            ? $this->stage
            : HiringProcessStage::from((string) $this->stage);

        return [
            'id' => $this->id,
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'stage_order' => $stage->order(),
            'notes' => $this->notes,
            'candidates_count' => $this->candidates_count,
            'created_by_name' => $this->createdByUser?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
