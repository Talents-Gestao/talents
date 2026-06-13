<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StrategicCalendarItemAttachment extends Model
{
    protected $fillable = [
        'strategic_calendar_item_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StrategicCalendarItem::class, 'strategic_calendar_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function downloadName(): string
    {
        return $this->original_name ?: 'anexo';
    }

    public function deleteStoredFile(): void
    {
        if ($this->path && $this->disk) {
            $disk = Storage::disk($this->disk);
            if ($disk->exists($this->path)) {
                $disk->delete($this->path);
            }
        }
    }
}
