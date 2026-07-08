<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class MetamorfoseDailyQuote
{
    /**
     * @return array{number: int, word: string, phrase: string, deck_title: string, card_label: string}|null
     */
    public function forDate(?CarbonInterface $date = null): ?array
    {
        /** @var list<array{number: int, word: string, phrase: string}> $quotes */
        $quotes = config('metamorfose_quotes', []);

        if ($quotes === []) {
            return null;
        }

        $date ??= Carbon::today();
        $index = ($date->dayOfYear - 1) % count($quotes);
        $quote = $quotes[$index];

        return [
            'number' => (int) $quote['number'],
            'word' => (string) $quote['word'],
            'phrase' => (string) $quote['phrase'],
            'deck_title' => 'Baralho da Metamorfose 2026',
            'card_label' => sprintf('Carta %d de %d', (int) $quote['number'], count($quotes)),
        ];
    }
}
