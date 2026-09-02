<?php

namespace App\Models;

use App\Enums\LandingInterestSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingInterestSubmission extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'message',
        'admin_notes',
        'is_qualified',
        'source',
        'created_by',
        'mail_sent_at',
        'mail_error',
    ];

    protected function casts(): array
    {
        return [
            'mail_sent_at' => 'datetime',
            'is_qualified' => 'boolean',
            'source' => LandingInterestSource::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceEnum(): LandingInterestSource
    {
        if ($this->source instanceof LandingInterestSource) {
            return $this->source;
        }

        return LandingInterestSource::tryFrom((string) $this->source) ?? LandingInterestSource::Site;
    }

    /**
     * @param  Builder<self>  $query
     * @param  array{
     *     search?: string,
     *     source?: string,
     *     qualified?: string,
     *     created_from?: string,
     *     created_to?: string
     * }  $filters
     * @return Builder<self>
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($like, $operator): void {
                $inner->where('name', $operator, $like)
                    ->orWhere('email', $operator, $like)
                    ->orWhere('company', $operator, $like)
                    ->orWhere('phone', $operator, $like);
            });
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '' && LandingInterestSource::tryFrom($source) !== null) {
            $query->where('source', $source);
        }

        $qualified = (string) ($filters['qualified'] ?? '');
        if ($qualified === 'yes') {
            $query->where('is_qualified', true);
        } elseif ($qualified === 'no') {
            $query->where('is_qualified', false);
        } elseif ($qualified === 'pending') {
            $query->whereNull('is_qualified');
        }

        if (filled($filters['created_from'] ?? null)) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (filled($filters['created_to'] ?? null)) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return $query;
    }
}
