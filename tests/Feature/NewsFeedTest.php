<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NewsCategory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_guest_cannot_access_news_feed(): void
    {
        $this->getJson('/client/noticias')->assertUnauthorized();
        $this->getJson('/admin/noticias')->assertUnauthorized();
    }

    public function test_client_receives_news_items_and_category_filters(): void
    {
        Http::fake([
            'news.google.com/*' => Http::response($this->sampleRss('RH no Brasil', 'https://example.com/rh-1'), 200),
        ]);

        $company = Company::query()->create([
            'name' => 'Empresa notícias',
            'cnpj' => '33.333.333/0001-33',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->getJson(route('client.news.feed', ['category' => NewsCategory::Hr->value]))
            ->assertOk()
            ->assertJsonPath('category', NewsCategory::Hr->value)
            ->assertJsonPath('items.0.title', 'RH no Brasil')
            ->assertJsonPath('items.0.category', NewsCategory::Hr->value)
            ->assertJsonStructure([
                'categories' => [
                    ['value', 'label', 'emoji'],
                ],
                'items' => [
                    ['id', 'title', 'summary', 'url', 'category'],
                ],
            ]);
    }

    public function test_admin_can_access_news_feed(): void
    {
        Http::fake([
            'news.google.com/*' => Http::response($this->sampleRss('Evento de negócios', 'https://example.com/evt'), 200),
        ]);

        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->getJson(route('admin.news.feed', ['category' => 'events']))
            ->assertOk()
            ->assertJsonPath('items.0.title', 'Evento de negócios');
    }

    public function test_feed_limits_to_five_items(): void
    {
        Http::fake([
            'news.google.com/*' => Http::response($this->sampleRssMany(8), 200),
        ]);

        $company = Company::query()->create([
            'name' => 'Empresa limite notícias',
            'cnpj' => '44.444.444/0001-44',
            'is_active' => true,
            'complaints_public_token' => (string) Str::uuid(),
        ]);
        $user = User::factory()->companyAdmin($company->id)->create();

        $this->actingAs($user)
            ->getJson(route('client.news.feed', ['category' => 'trends']))
            ->assertOk()
            ->assertJsonCount(5, 'items');
    }

    private function sampleRss(string $title, string $link): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>News</title>
    <item>
      <title>{$title}</title>
      <link>{$link}</link>
      <pubDate>Mon, 13 Jul 2026 12:00:00 GMT</pubDate>
      <description><![CDATA[<img src="https://example.com/img.jpg"/><p>Resumo da notícia.</p>]]></description>
      <source>Portal Demo</source>
    </item>
  </channel>
</rss>
XML;
    }

    private function sampleRssMany(int $count): string
    {
        $items = '';
        for ($i = 1; $i <= $count; $i++) {
            $items .= <<<XML
    <item>
      <title>Notícia {$i}</title>
      <link>https://example.com/n-{$i}</link>
      <pubDate>Mon, 13 Jul 2026 12:0{$i}:00 GMT</pubDate>
      <description>Resumo {$i}</description>
    </item>

XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>News</title>
{$items}
  </channel>
</rss>
XML;
    }
}
