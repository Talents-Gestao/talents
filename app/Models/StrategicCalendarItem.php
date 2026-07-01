<?php

namespace App\Models;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicCalendarItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'kind',
        'occurs_on',
        'recurrence',
        'recurrence_ends_on',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'occurs_on' => 'date',
            'recurrence_ends_on' => 'date',
            'kind' => StrategicCalendarItemKind::class,
            'recurrence' => StrategicCalendarRecurrence::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StrategicCalendarItemAttachment::class, 'strategic_calendar_item_id')
            ->orderBy('id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(StrategicCalendarCompletion::class, 'strategic_calendar_item_id');
    }

    public function deleteAllAttachments(): void
    {
        foreach ($this->attachments as $attachment) {
            $attachment->deleteStoredFile();
            $attachment->delete();
        }
    }

    /**
     * Itens visíveis para a empresa: globais (company_id null) ou específicos da empresa.
     */
    public function scopeForCompany(Builder $query, Company $company): Builder
    {
        return $query->where(function (Builder $q) use ($company) {
            $q->whereNull('company_id')
                ->orWhere('company_id', $company->id);
        });
    }
}
