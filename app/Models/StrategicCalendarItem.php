<?php

namespace App\Models;

use App\Enums\StrategicCalendarItemKind;
use App\Enums\StrategicCalendarRecurrence;
use App\Enums\StrategicCalendarSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicCalendarItem extends Model
{
    protected $fillable = [
        'title',
        'description',
        'kind',
        'occurs_on',
        'ends_on',
        'recurrence',
        'recurrence_ends_on',
        'company_id',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurs_on' => 'date',
            'ends_on' => 'date',
            'recurrence_ends_on' => 'date',
            'kind' => StrategicCalendarItemKind::class,
            'recurrence' => StrategicCalendarRecurrence::class,
            'source' => StrategicCalendarSource::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'strategic_calendar_item_company',
            'strategic_calendar_item_id',
            'company_id',
        )->withTimestamps();
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

    public function isCompanyAgenda(): bool
    {
        return $this->source === StrategicCalendarSource::Company;
    }

    public function isTalentsAgenda(): bool
    {
        return $this->source !== StrategicCalendarSource::Company;
    }

    public function deleteAllAttachments(): void
    {
        foreach ($this->attachments as $attachment) {
            $attachment->deleteStoredFile();
            $attachment->delete();
        }
    }

    /**
     * Filtra pela origem da agenda (Talents ou Empresa).
     */
    public function scopeOfAgenda(Builder $query, ?string $agenda): Builder
    {
        if ($agenda === StrategicCalendarSource::Company->value) {
            return $query->where('source', StrategicCalendarSource::Company);
        }

        if ($agenda === StrategicCalendarSource::Talents->value) {
            return $query->where(function (Builder $q) {
                $q->whereNull('source')
                    ->orWhere('source', StrategicCalendarSource::Talents);
            });
        }

        return $query;
    }

    /**
     * Itens visíveis para a empresa: globais, vinculados diretamente ou via pivot.
     */
    public function scopeForCompany(Builder $query, Company $company): Builder
    {
        return $query->where(function (Builder $q) use ($company) {
            $q->where(function (Builder $global) {
                $global->whereNull('company_id')
                    ->whereDoesntHave('companies')
                    ->where(function (Builder $source) {
                        $source->whereNull('source')
                            ->orWhere('source', StrategicCalendarSource::Talents);
                    });
            })
                ->orWhere(function (Builder $owned) use ($company) {
                    $owned->where('company_id', $company->id);
                })
                ->orWhereHas('companies', fn (Builder $c) => $c->where('companies.id', $company->id));
        });
    }
}
