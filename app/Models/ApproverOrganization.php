<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organização aprovadora dos documentos NR-1. Modelo genérico: não assume quem ela é na prática.
 */
class ApproverOrganization extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(UserWorkspace::class);
    }
}
