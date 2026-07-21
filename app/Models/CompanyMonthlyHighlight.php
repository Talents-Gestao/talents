<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonthlyHighlightCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CompanyMonthlyHighlight extends Model
{
    protected $fillable = [
        'company_id',
        'company_employee_id',
        'person_name',
        'category',
        'year',
        'month',
        'photo_path',
        'photo_disk',
        'description',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => MonthlyHighlightCategory::class,
            'year' => 'integer',
            'month' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(CompanyEmployee::class, 'company_employee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function photoUrl(): ?string
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return null;
        }

        return Storage::disk($this->photo_disk ?: 'public')->url($this->photo_path);
    }

    public function deletePhoto(): void
    {
        if ($this->photo_path === null || $this->photo_path === '') {
            return;
        }

        $disk = $this->photo_disk ?: 'public';
        if (Storage::disk($disk)->exists($this->photo_path)) {
            Storage::disk($disk)->delete($this->photo_path);
        }
    }

    public function periodLabel(): string
    {
        $month = str_pad((string) $this->month, 2, '0', STR_PAD_LEFT);

        return $month.'/'.$this->year;
    }
}
