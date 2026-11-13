<?php

// Cartão do quadro SUPORTE: «NOTICIAS» (feed por categoria no admin e no portal).
// O «resumo diário» do cartão é uma conversa colada, não um requisito: não testável.
// Já cobertos: filtro por categoria, acesso do admin e limite de 5 itens (NewsFeedTest). Sem rede: RSS falso.

namespace Tests\Feature\Acceptance\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    private const CARD_CATEGORIES = [
        'hr' => 'RH',
        'entrepreneurship' => 'Empreendedorismo',
        'accounting' => 'Contabilidade',
        'health' => 'Saúde',
        'trends' => 'Tendências',
        'launches' => 'lançamentos',
        'important_dates' => 'Datas importantes',
        'events' => 'Eventos',
    ];

    public function test_admin_and_portal_offer_the_eight_news_categories_of_the_card(): void
    {
        Cache::flush();
        Http::fake(['news.google.com/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>N</title></channel></rss>', 200)]);

        $company = Company::query()->create(['name' => 'Empresa notícias', 'is_active' => true, 'complaints_public_token' => (string) Str::uuid()]);

        foreach ([
            [User::factory()->superAdmin()->create(), 'admin.news.feed'],
            [User::factory()->companyAdmin($company->id)->create(), 'client.news.feed'],
        ] as [$user, $route]) {
            $categories = collect(
                $this->actingAs($user)->getJson(route($route, ['category' => 'hr']))->assertOk()->json('categories')
            )->pluck('label', 'value')->except('all'); // «all» é o filtro «Todas»

            $this->assertEqualsCanonicalizing(array_keys(self::CARD_CATEGORIES), $categories->keys()->all(), $route);
            foreach (self::CARD_CATEGORIES as $value => $word) {
                $this->assertStringContainsStringIgnoringCase($word, $categories[$value], "{$route}: {$value}");
            }
        }
    }
}
