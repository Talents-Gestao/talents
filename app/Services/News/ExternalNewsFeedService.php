<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Enums\NewsCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ExternalNewsFeedService
{
    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     summary: string,
     *     url: string,
     *     image_url: string|null,
     *     source: string|null,
     *     published_at: string|null,
     *     category: string,
     *     category_label: string,
     *     category_emoji: string
     * }>
     */
    public function recent(?string $categoryFilter = null, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('news.limit', 5);
        $category = $this->resolveCategory($categoryFilter);

        $items = $category === null
            ? $this->recentAcrossCategories($limit)
            : $this->recentForCategory($category, $limit);

        return $items->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentAcrossCategories(int $limit): Collection
    {
        $pooled = collect(NewsCategory::cases())
            ->flatMap(fn (NewsCategory $category) => $this->cachedItemsFor($category)->take(3))
            ->sortByDesc(fn (array $item) => $item['published_at'] ?? '')
            ->unique('url')
            ->take($limit)
            ->values();

        return $pooled;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function recentForCategory(NewsCategory $category, int $limit): Collection
    {
        return $this->cachedItemsFor($category)->take($limit)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function cachedItemsFor(NewsCategory $category): Collection
    {
        $ttl = max(60, (int) config('news.cache_ttl', 3600));
        $cacheKey = 'news.feed.v1.'.$category->value;

        /** @var list<array<string, mixed>> $items */
        $items = Cache::remember($cacheKey, $ttl, function () use ($category): array {
            return $this->fetchCategoryFeed($category);
        });

        return collect($items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCategoryFeed(NewsCategory $category): array
    {
        $url = $this->googleNewsRssUrl($category);

        try {
            $response = Http::timeout((int) config('news.http_timeout', 8))
                ->withHeaders([
                    'User-Agent' => 'TalentsNewsBot/1.0 (+https://talents.local)',
                    'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Falha ao obter feed de notícias.', [
                    'category' => $category->value,
                    'status' => $response->status(),
                ]);

                return $this->fallbackItems($category);
            }

            $parsed = $this->parseRss($response->body(), $category);

            return $parsed !== [] ? $parsed : $this->fallbackItems($category);
        } catch (Throwable $e) {
            Log::warning('Erro ao consultar feed de notícias.', [
                'category' => $category->value,
                'message' => $e->getMessage(),
            ]);

            return $this->fallbackItems($category);
        }
    }

    private function googleNewsRssUrl(NewsCategory $category): string
    {
        return 'https://news.google.com/rss/search?'.http_build_query([
            'q' => $category->searchQuery(),
            'hl' => 'pt-BR',
            'gl' => 'BR',
            'ceid' => 'BR:pt-419',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseRss(string $xmlBody, NewsCategory $category): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || ! isset($xml->channel->item)) {
            return [];
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            $title = $this->cleanText((string) $item->title);
            $link = trim((string) $item->link);
            $description = (string) ($item->description ?? '');
            $summary = $this->cleanText(strip_tags($description));
            $publishedRaw = (string) ($item->pubDate ?? '');
            $source = null;

            if (isset($item->source)) {
                $source = $this->cleanText((string) $item->source);
            }

            if ($title === '' || $link === '') {
                continue;
            }

            $publishedAt = null;
            if ($publishedRaw !== '') {
                try {
                    $publishedAt = Carbon::parse($publishedRaw)->utc()->toIso8601String();
                } catch (Throwable) {
                    $publishedAt = null;
                }
            }

            $items[] = [
                'id' => sha1($category->value.'|'.$link),
                'title' => $title,
                'summary' => Str::limit($summary !== '' ? $summary : $title, 180),
                'url' => $link,
                'image_url' => $this->extractImageUrl($item, $description),
                'source' => $source,
                'published_at' => $publishedAt,
                'category' => $category->value,
                'category_label' => $category->label(),
                'category_emoji' => $category->emoji(),
            ];
        }

        return $items;
    }

    private function extractImageUrl(\SimpleXMLElement $item, string $descriptionHtml): ?string
    {
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->content)) {
                $attributes = $media->content->attributes();
                $url = trim((string) ($attributes['url'] ?? ''));
                if ($url !== '' && Str::startsWith($url, ['http://', 'https://'])) {
                    return $url;
                }
            }
            if (isset($media->thumbnail)) {
                $attributes = $media->thumbnail->attributes();
                $url = trim((string) ($attributes['url'] ?? ''));
                if ($url !== '' && Str::startsWith($url, ['http://', 'https://'])) {
                    return $url;
                }
            }
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionHtml, $matches) === 1) {
            $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (Str::startsWith($url, ['http://', 'https://'])) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Itens estáticos quando a fonte externa falha (mantém o drawer útil offline).
     *
     * @return list<array<string, mixed>>
     */
    private function fallbackItems(NewsCategory $category): array
    {
        $samples = [
            NewsCategory::Hr->value => [
                'Como lideranças podem fortalecer a cultura organizacional no dia a dia',
                'Cinco práticas de feedback contínuo que reduzem rotatividade',
            ],
            NewsCategory::Entrepreneurship->value => [
                'Empreendedores revisam modelo de negócio em cenários de incerteza',
                'Captação e eficiência operacional voltaram ao centro da estratégia',
            ],
            NewsCategory::Accounting->value => [
                'Atualizações trabalhistas e fiscais que pedem atenção do RH',
                'Checklist de conformidade para fechar o mês com segurança',
            ],
            NewsCategory::Health->value => [
                'Programas de bem-estar passam a integrar indicadores de desempenho',
                'Saúde mental no trabalho: o que empresas estão priorizando',
            ],
            NewsCategory::Trends->value => [
                'Tendências de mercado de talentos para os próximos meses',
                'Oportunidades em reskilling e novos papéis híbridos',
            ],
            NewsCategory::Launches->value => [
                'Ferramentas digitais aceleram rotinas de gestão de pessoas',
                'Novidades em people analytics para times de RH',
            ],
            NewsCategory::ImportantDates->value => [
                'Datas fiscais e obrigações que impactam a agenda do mês',
                'Calendário de feriados e janelas críticas para o RH',
            ],
            NewsCategory::Events->value => [
                'Encontros e congressos de RH e gestão nos próximos dias',
                'Eventos de negócios e networking recomendados para lideranças',
            ],
        ];

        $titles = $samples[$category->value] ?? ['Atualizações relevantes para a sua agenda'];

        return collect($titles)
            ->take(2)
            ->map(function (string $title, int $index) use ($category): array {
                return [
                    'id' => 'fallback-'.$category->value.'-'.$index,
                    'title' => $title,
                    'summary' => 'Resumo sugestivo enquanto a fonte externa está indisponível. Em breve as notícias voltarão a ser atualizadas automaticamente.',
                    'url' => 'https://news.google.com/search?'.http_build_query([
                        'q' => $category->searchQuery(),
                        'hl' => 'pt-BR',
                        'gl' => 'BR',
                        'ceid' => 'BR:pt-419',
                    ]),
                    'image_url' => null,
                    'source' => 'Talents',
                    'published_at' => now()->subHours($index + 1)->utc()->toIso8601String(),
                    'category' => $category->value,
                    'category_label' => $category->label(),
                    'category_emoji' => $category->emoji(),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveCategory(?string $categoryFilter): ?NewsCategory
    {
        if ($categoryFilter === null || $categoryFilter === '' || $categoryFilter === 'all') {
            return null;
        }

        return NewsCategory::tryFrom($categoryFilter);
    }

    private function cleanText(string $value): string
    {
        return trim(html_entity_decode(preg_replace('/\s+/u', ' ', $value) ?? $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
