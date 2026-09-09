<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CommercialProcess extends Model
{
    protected $fillable = [
        'title',
        'summary',
        'body_html',
        'file_path',
        'file_name',
        'sort_order',
        'is_published',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function deleteFile(): void
    {
        if (! $this->file_path) {
            return;
        }

        if (Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->forceFill([
            'file_path' => null,
            'file_name' => null,
        ])->save();
    }
}
