<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sugere pares de cadastros de colaborador que podem ser a mesma pessoa, dentro de UMA empresa: e-mail igual
 * (sem diferença de caixa) ou nome muito parecido (sem acentos, caixa, pontuação e texto entre parênteses; igual,
 * ou a até 2 letras de distância em nomes longos). Só sugere: nunca une sozinho.
 */
final class FeedbackEmployeeDuplicateFinder
{
    public const MAX_EMPLOYEES = 3000;

    public const MAX_PAIRS = 100;

    public static function normalizeName(?string $name): string
    {
        $name = (string) preg_replace('/\([^)]*\)/u', ' ', (string) $name);
        $name = Str::lower(Str::ascii($name));
        $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /**
     * @return list<array{a: CompanyEmployee, b: CompanyEmployee, reason: string, reason_label: string}>
     */
    public function forCompany(Company $company): array
    {
        /** @var Collection<int, CompanyEmployee> $employees */
        $employees = CompanyEmployee::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('merged_into_employee_id')
            ->withCount('feedbackSessions')
            ->orderBy('id')
            ->limit(self::MAX_EMPLOYEES)
            ->get();

        $pairs = [];
        $seen = [];
        $add = function (CompanyEmployee $a, CompanyEmployee $b, string $reason) use (&$pairs, &$seen) {
            $key = min($a->id, $b->id).'-'.max($a->id, $b->id);
            if (isset($seen[$key]) || count($pairs) >= self::MAX_PAIRS) {
                return;
            }
            $seen[$key] = true;
            $pairs[] = [
                'a' => $a->id < $b->id ? $a : $b,
                'b' => $a->id < $b->id ? $b : $a,
                'reason' => $reason,
                'reason_label' => match ($reason) {
                    'email' => 'Mesmo e-mail',
                    'same_name' => 'Mesmo nome (ignorando acentos e maiúsculas)',
                    default => 'Nome muito parecido',
                },
            ];
        };

        foreach ($employees->filter(fn ($e) => filled($e->email))->groupBy(fn ($e) => Str::lower(trim((string) $e->email))) as $group) {
            $list = $group->values();
            for ($i = 1; $i < $list->count(); $i++) {
                $add($list[0], $list[$i], 'email');
            }
        }

        // Nomes: agrupa pela primeira palavra para não comparar todos com todos.
        $byFirst = $employees->groupBy(fn ($e) => explode(' ', self::normalizeName($e->name).' ')[0]);
        foreach ($byFirst as $first => $group) {
            if ($first === '') {
                continue;
            }
            $list = $group->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $na = self::normalizeName($list[$i]->name);
                    $nb = self::normalizeName($list[$j]->name);
                    if ($na === $nb) {
                        $add($list[$i], $list[$j], 'same_name');
                    } elseif (min(strlen($na), strlen($nb)) >= 8 && levenshtein($na, $nb) <= 2) {
                        $add($list[$i], $list[$j], 'similar_name');
                    }
                }
            }
        }

        return $pairs;
    }
}
