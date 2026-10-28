<?php

declare(strict_types=1);

namespace App\Support\Complaints;

use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

class ComplaintProtocol
{
    public const PATTERN = '/^DEN-\d{4}-\d{5}$/';

    public static function generate(int $companyId, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = sprintf('DEN-%d-', $year);

        return DB::transaction(function () use ($companyId, $year, $prefix): string {
            $last = Complaint::query()
                ->where('company_id', $companyId)
                ->where('protocol', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('protocol')
                ->value('protocol');

            $next = 1;
            if (is_string($last) && preg_match('/^DEN-\d{4}-(\d{5})$/', $last, $matches) === 1) {
                $next = (int) $matches[1] + 1;
            }

            return sprintf('DEN-%d-%05d', $year, $next);
        });
    }

    public static function normalize(string $raw): string
    {
        $compact = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $raw));

        if (preg_match('/^DEN(\d{4})(\d{1,5})$/', $compact, $matches) === 1) {
            return sprintf('DEN-%s-%05d', $matches[1], (int) $matches[2]);
        }

        return strtoupper(trim($raw));
    }

    public static function isValid(string $protocol): bool
    {
        return preg_match(self::PATTERN, $protocol) === 1;
    }
}
