<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MetamorfoseDailyQuote;
use Carbon\Carbon;
use Tests\TestCase;

class MetamorfoseDailyQuoteTest extends TestCase
{
    public function test_returns_quote_for_day_of_year(): void
    {
        $quote = app(MetamorfoseDailyQuote::class)->forDate(Carbon::create(2026, 1, 1));

        $this->assertNotNull($quote);
        $this->assertSame(1, $quote['number']);
        $this->assertSame('Propósito', $quote['word']);
        $this->assertSame('Quando o porquê é forte, o caminho se revela.', $quote['phrase']);
        $this->assertSame('Baralho da Metamorfose 2026', $quote['deck_title']);
        $this->assertSame('Carta 1 de 52', $quote['card_label']);
    }

    public function test_cycles_after_fifty_two_cards(): void
    {
        $first = app(MetamorfoseDailyQuote::class)->forDate(Carbon::create(2026, 1, 1));
        $again = app(MetamorfoseDailyQuote::class)->forDate(Carbon::create(2026, 2, 22));

        $this->assertSame($first['number'], $again['number']);
        $this->assertSame($first['word'], $again['word']);
    }
}
