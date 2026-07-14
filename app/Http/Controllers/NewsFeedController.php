<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NewsCategory;
use App\Services\News\ExternalNewsFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NewsFeedController extends Controller
{
    public function __invoke(Request $request, ExternalNewsFeedService $newsFeed): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in(['all', ...array_column(NewsCategory::cases(), 'value')])],
        ]);

        $category = $validated['category'] ?? 'all';

        return response()->json([
            'category' => $category,
            'categories' => [
                [
                    'value' => 'all',
                    'label' => 'Todas',
                    'emoji' => '📰',
                ],
                ...NewsCategory::options(),
            ],
            'items' => $newsFeed->recent($category === 'all' ? null : $category),
        ]);
    }
}
