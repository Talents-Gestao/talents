<?php

declare(strict_types=1);

namespace App\Support\Finance;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Páginas de tamanho variável (grupos que não podem ser cortados).
 * lastPage e o intervalo from/to seguem as páginas reais, não um corte fixo.
 */
final class GroupedLengthAwarePaginator extends LengthAwarePaginator
{
    public function __construct(
        mixed $items,
        int $total,
        int $perPage,
        int $currentPage,
        private readonly int $pageCount,
        private readonly ?int $rowFrom,
        private readonly ?int $rowTo,
        array $options = [],
    ) {
        parent::__construct($items, $total, $perPage, $currentPage, $options);
    }

    public function lastPage(): int
    {
        return max(1, $this->pageCount);
    }

    public function firstItem(): ?int
    {
        if (count($this->items) === 0) {
            return null;
        }

        return $this->rowFrom;
    }

    public function lastItem(): ?int
    {
        if (count($this->items) === 0) {
            return null;
        }

        return $this->rowTo;
    }
}
